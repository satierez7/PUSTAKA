"""PUSTAKA - Sistem Informasi Perpustakaan (Flask + SQLite).

Tiga peran:
  admin    : semua fitur petugas + kelola petugas + pengaturan
  petugas  : data buku, kategori, anggota, peminjaman, pengembalian, laporan
  anggota  : cari katalog, ajukan pinjam, pinjaman saya, favorit, rating
"""
import csv
import io
import os
import re
import sqlite3
from datetime import date, timedelta
from functools import wraps

from flask import (Flask, Response, abort, flash, g, redirect, render_template,
                   request, session, url_for)
from werkzeug.security import check_password_hash, generate_password_hash

BASE = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.environ.get("PUSTAKA_DB", os.path.join(BASE, "pustaka.db"))

app = Flask(__name__)
app.secret_key = os.environ.get("SECRET_KEY", "ganti-kunci-rahasia-ini")

MIN_PASSWORD = 6
STAFF_ROLES = ("admin", "petugas")
DEFAULTS = {"loan_days": 7, "fine_per_day": 500, "max_loans": 3}
MONTHS = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"]
# warna sampul buku (latar, teks)
COVERS = [("#5b3a1e", "#f6ead2"), ("#34503a", "#eef3e6"), ("#243a5e", "#e8eefb"),
          ("#dcc7a0", "#3a2512"), ("#8a2f22", "#fbe9e4"), ("#8a5a2b", "#fbf0dc"),
          ("#4d2f4f", "#f5e8f6"), ("#2f5a58", "#e4f3f1")]

BOOK_SELECT = """
SELECT b.*, c.name AS category,
       (SELECT ROUND(AVG(r.stars), 1) FROM ratings r WHERE r.book_id = b.id) AS rating,
       (SELECT COUNT(*) FROM ratings r WHERE r.book_id = b.id) AS rating_count
FROM books b LEFT JOIN categories c ON c.id = b.category_id
"""
LOAN_SELECT = """
SELECT l.*, b.title, b.author, u.name AS member_name, u.member_code
FROM loans l JOIN books b ON b.id = l.book_id JOIN users u ON u.id = l.member_id
"""


# ====================================================================== database
def db():
    if "db" not in g:
        g.db = sqlite3.connect(DB_PATH)
        g.db.row_factory = sqlite3.Row
        g.db.execute("PRAGMA foreign_keys = ON")
    return g.db


@app.teardown_appcontext
def close_db(_exc=None):
    conn = g.pop("db", None)
    if conn is not None:
        conn.close()


def q_all(sql, *args):
    return db().execute(sql, args).fetchall()


def q_one(sql, *args):
    return db().execute(sql, args).fetchone()


def q_val(sql, *args):
    return db().execute(sql, args).fetchone()[0]


def run(sql, *args, commit=True):
    cur = db().execute(sql, args)
    if commit:
        db().commit()
    return cur


def cfg(key):
    row = q_one("SELECT value FROM settings WHERE key = ?", key)
    return int(row["value"]) if row else DEFAULTS[key]


def init_db(seed=True):
    """Buat tabel dari schema.sql, lalu isi data contoh kalau database masih kosong."""
    con = sqlite3.connect(DB_PATH)
    with open(os.path.join(BASE, "schema.sql"), encoding="utf-8") as f:
        con.executescript(f.read())
    con.commit()
    if seed and not con.execute("SELECT COUNT(*) FROM users").fetchone()[0]:
        seed_data(con)
        con.commit()
    con.close()


def seed_data(con):
    pw = {p: generate_password_hash(p) for p in ("admin123", "petugas123", "anggota123")}
    con.execute("INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)",
                ("Administrator", "admin@pustaka.id", pw["admin123"], "admin"))
    con.execute("INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)",
                ("Petugas Pustaka", "petugas@pustaka.id", pw["petugas123"], "petugas"))
    members = [
        ("Budi Santoso", "budi@pustaka.id", "081234567890", "Jakarta"),
        ("Siti Aminah", "siti@pustaka.id", "081298765432", "Bekasi"),
        ("Rahmat Hidayat", "rahmat@pustaka.id", "081311122233", "Depok"),
        ("Dewi Lestari", "dewi@pustaka.id", "081355566677", "Bogor"),
        ("Andi Pratama", "andi@pustaka.id", "081377788899", "Tangerang"),
    ]
    for name, email, phone, addr in members:
        cur = con.execute(
            "INSERT INTO users (name, email, password_hash, role, phone, address) "
            "VALUES (?,?,?,'anggota',?,?)", (name, email, pw["anggota123"], phone, addr))
        con.execute("UPDATE users SET member_code=? WHERE id=?",
                    (f"AG{cur.lastrowid:04d}", cur.lastrowid))

    for c in ["Fiksi", "Sains", "Teknologi", "Sejarah", "Pengembangan Diri"]:
        con.execute("INSERT INTO categories (name) VALUES (?)", (c,))
    books = [
        ("Laskar Pelangi", "Andrea Hirata", "Bentang", 2005, "Fiksi", "A-01", 3,
         "Kisah sepuluh anak di Belitung yang berjuang menempuh pendidikan dengan segala keterbatasan."),
        ("Bumi Manusia", "Pramoedya Ananta Toer", "Lentera Dipantara", 1980, "Fiksi", "A-02", 2,
         "Novel pertama Tetralogi Buru tentang Minke, pelajar pribumi di masa kolonial Hindia Belanda."),
        ("Atomic Habits", "James Clear", "Gramedia", 2019, "Pengembangan Diri", "C-01", 4,
         "Panduan membangun kebiasaan baik lewat perubahan kecil yang dilakukan konsisten."),
        ("Filosofi Teras", "Henry Manampiring", "Kompas", 2018, "Pengembangan Diri", "C-02", 3,
         "Pengantar filsafat Stoa untuk menghadapi emosi negatif dan menjalani hidup lebih tenang."),
        ("Sapiens", "Yuval Noah Harari", "KPG", 2017, "Sejarah", "D-01", 2,
         "Sejarah singkat umat manusia, dari zaman batu sampai era modern."),
        ("Negeri 5 Menara", "Ahmad Fuadi", "Gramedia", 2009, "Fiksi", "A-03", 3,
         "Perjalanan enam santri di pesantren dengan semangat man jadda wajada."),
        ("Clean Code", "Robert C. Martin", "Prentice Hall", 2008, "Teknologi", "T-01", 1,
         "Prinsip dan praktik menulis kode yang bersih, mudah dibaca, dan mudah dirawat."),
        ("Cantik Itu Luka", "Eka Kurniawan", "Gramedia", 2002, "Fiksi", "A-04", 2,
         "Novel epik tentang Dewi Ayu dan keturunannya di tengah sejarah panjang Nusantara."),
        ("Bumi", "Tere Liye", "Gramedia", 2014, "Fiksi", "A-05", 3,
         "Petualangan Raib, remaja yang menemukan dunia paralel di balik kehidupan sehari-hari."),
        ("A Brief History of Time", "Stephen Hawking", "Bantam", 1988, "Sains", "S-01", 2,
         "Pengantar populer tentang alam semesta, lubang hitam, dan asal-usul waktu."),
        ("Cosmos", "Carl Sagan", "Random House", 1980, "Sains", "S-02", 2,
         "Perjalanan menjelajahi alam semesta dan tempat manusia di dalamnya."),
        ("The Pragmatic Programmer", "Andrew Hunt & David Thomas", "Addison-Wesley", 1999,
         "Teknologi", "T-02", 2, "Kumpulan kiat praktis untuk menjadi programmer yang efektif."),
    ]
    for title, author, pub, year, cat, shelf, total, desc in books:
        cid = con.execute("SELECT id FROM categories WHERE name=?", (cat,)).fetchone()[0]
        con.execute(
            "INSERT INTO books (title, author, publisher, year, category_id, shelf, description, "
            "stock_total, stock_available) VALUES (?,?,?,?,?,?,?,?,?)",
            (title, author, pub, year, cid, shelf, desc, total, total))

    staff_id = con.execute("SELECT id FROM users WHERE email='petugas@pustaka.id'").fetchone()[0]
    t = date.today()

    def d(off):
        return (t + timedelta(days=off)).isoformat() if off is not None else None

    def add(email, title, status, loan=None, due=None, ret=None, fine=0, req=None):
        uid = con.execute("SELECT id FROM users WHERE email=?", (email,)).fetchone()[0]
        bid = con.execute("SELECT id FROM books WHERE title=?", (title,)).fetchone()[0]
        requested = req if req is not None else (loan if loan is not None else 0)
        con.execute(
            "INSERT INTO loans (book_id, member_id, status, requested_at, loan_date, due_date, "
            "return_date, fine, processed_by, returned_by) VALUES (?,?,?,?,?,?,?,?,?,?)",
            (bid, uid, status, d(requested), d(loan), d(due), d(ret), fine,
             staff_id if status != "menunggu" else None,
             staff_id if status == "dikembalikan" else None))
        if status == "dipinjam":
            con.execute("UPDATE books SET stock_available = stock_available - 1 WHERE id=?", (bid,))

    add("budi@pustaka.id", "Bumi Manusia", "dipinjam", loan=-1, due=6)
    add("budi@pustaka.id", "Atomic Habits", "dipinjam", loan=-10, due=-3)       # terlambat 3 hari
    add("budi@pustaka.id", "Laskar Pelangi", "menunggu", req=0)
    add("siti@pustaka.id", "Sapiens", "dipinjam", loan=-5, due=2)
    add("siti@pustaka.id", "Clean Code", "dipinjam", loan=-2, due=5)
    add("siti@pustaka.id", "Negeri 5 Menara", "dikembalikan", loan=-30, due=-23, ret=-24)
    add("siti@pustaka.id", "Filosofi Teras", "dikembalikan", loan=-40, due=-33, ret=-30, fine=1500)
    add("rahmat@pustaka.id", "Cosmos", "dipinjam", loan=-12, due=-5)             # terlambat 5 hari
    add("rahmat@pustaka.id", "A Brief History of Time", "dikembalikan", loan=-25, due=-18, ret=-18)
    add("dewi@pustaka.id", "Cantik Itu Luka", "menunggu", req=-1)
    add("andi@pustaka.id", "Bumi", "dikembalikan", loan=-20, due=-13, ret=-14)

    def uid_of(email):
        return con.execute("SELECT id FROM users WHERE email=?", (email,)).fetchone()[0]

    def bid_of(title):
        return con.execute("SELECT id FROM books WHERE title=?", (title,)).fetchone()[0]

    for email, title, stars in [("siti@pustaka.id", "Negeri 5 Menara", 5),
                                ("siti@pustaka.id", "Filosofi Teras", 4),
                                ("rahmat@pustaka.id", "A Brief History of Time", 5),
                                ("andi@pustaka.id", "Bumi", 4),
                                ("budi@pustaka.id", "Bumi Manusia", 5)]:
        con.execute("INSERT INTO ratings (member_id, book_id, stars) VALUES (?,?,?)",
                    (uid_of(email), bid_of(title), stars))
    for email, title in [("budi@pustaka.id", "Atomic Habits"), ("budi@pustaka.id", "Filosofi Teras"),
                         ("siti@pustaka.id", "Sapiens")]:
        con.execute("INSERT INTO favorites (member_id, book_id) VALUES (?,?)",
                    (uid_of(email), bid_of(title)))


@app.cli.command("init-db")
def init_db_command():
    init_db()
    print("Database siap.")


# ====================================================================== helper umum
def today_str():
    return date.today().isoformat()


def parse_date(s, fallback=None):
    try:
        return date.fromisoformat(str(s)[:10])
    except (TypeError, ValueError):
        return fallback


@app.template_filter("rupiah")
def f_rupiah(v):
    return "Rp" + f"{int(v or 0):,}".replace(",", ".")


@app.template_filter("num")
def f_num(v):
    return f"{int(v or 0):,}".replace(",", ".")


@app.template_filter("tgl")
def f_tgl(v):
    d = parse_date(v) if v else None
    return f"{d.day} {MONTHS[d.month - 1]} {d.year}" if d else "-"


@app.template_filter("initials")
def f_initials(name):
    parts = [p for p in (name or "").split() if p]
    return "".join(p[0] for p in parts[:2]).upper() or "?"


@app.template_filter("cover_style")
def f_cover_style(bid):
    bg, fg = COVERS[(int(bid) - 1) % len(COVERS)]
    return f"background:{bg};color:{fg}"


@app.template_filter("stars")
def f_stars(r):
    n = int(round(r or 0))
    return "★" * n + "☆" * (5 - n)


def decorate(rows):
    """Tambahkan kode, label status, hari terlambat, dan estimasi denda ke baris peminjaman."""
    out = []
    t = date.today()
    per_day = cfg("fine_per_day")
    for r in rows:
        d = dict(r)
        d["code"] = f"PJ-{r['id']:04d}"
        late = left = 0
        st = r["status"]
        if st == "dipinjam":
            diff = (date.fromisoformat(r["due_date"]) - t).days
            late, left = max(-diff, 0), max(diff, 0)
            if late:
                d.update(label=f"Terlambat {late} hari", tone="red", state="Terlambat", state_tone="red")
            elif left == 0:
                d.update(label="Jatuh tempo hari ini", tone="yellow", state="Dipinjam", state_tone="blue")
            else:
                d.update(label=f"Sisa {left} hari", tone="green", state="Dipinjam", state_tone="blue")
        elif st == "menunggu":
            d.update(label="Menunggu persetujuan", tone="yellow", state="Menunggu", state_tone="yellow")
        elif st == "dikembalikan":
            d.update(label="Dikembalikan", tone="green", state="Dikembalikan", state_tone="green")
        elif st == "ditolak":
            d.update(label="Ditolak", tone="red", state="Ditolak", state_tone="red")
        else:
            d.update(label="Dibatalkan", tone="brown", state="Dibatalkan", state_tone="brown")
        d["late_days"], d["days_left"], d["est_fine"] = late, left, late * per_day
        d["shown_fine"] = r["fine"] if st == "dikembalikan" else late * per_day
        out.append(d)
    return out


def categories():
    return q_all("SELECT c.*, (SELECT COUNT(*) FROM books b WHERE b.category_id=c.id) AS n "
                 "FROM categories c ORDER BY c.name")


EMAIL_RE = re.compile(r"^[^@\s]+@[^@\s]+\.[^@\s]+$")


# ====================================================================== akses & sesi
@app.before_request
def load_user():
    g.user = None
    if request.endpoint == "static":
        return
    uid = session.get("uid")
    if uid:
        u = q_one("SELECT * FROM users WHERE id=?", uid)
        if u and u["status"] == "aktif":
            g.user = u
        else:
            session.clear()
            if u:
                flash("Akunmu dinonaktifkan. Hubungi petugas perpustakaan.", "error")


@app.context_processor
def inject_globals():
    ctx = {"me": g.get("user"), "max_loans": cfg("max_loans"),
           "fine_per_day": cfg("fine_per_day"), "loan_days": cfg("loan_days")}
    if g.get("user") and g.user["role"] in STAFF_ROLES:
        ctx["pending_count"] = q_val("SELECT COUNT(*) FROM loans WHERE status='menunggu'")
    return ctx


def roles_required(*roles):
    def deco(view):
        @wraps(view)
        def wrapped(*a, **kw):
            if not g.user:
                return redirect(url_for("login"))
            if g.user["role"] not in roles:
                flash("Halaman itu tidak tersedia untuk akunmu.", "error")
                return redirect(url_for("index"))
            return view(*a, **kw)
        return wrapped
    return deco


staff_required = roles_required(*STAFF_ROLES)
admin_required = roles_required("admin")
member_required = roles_required("anggota")


def login_required(view):
    @wraps(view)
    def wrapped(*a, **kw):
        if not g.user:
            return redirect(url_for("login"))
        return view(*a, **kw)
    return wrapped


@app.errorhandler(404)
def not_found(_e):
    return render_template("error.html"), 404


# ====================================================================== auth & profil
@app.route("/")
def index():
    if not g.user:
        return redirect(url_for("login"))
    return redirect(url_for("dashboard" if g.user["role"] in STAFF_ROLES else "catalog"))


@app.route("/masuk", methods=["GET", "POST"])
def login():
    if g.user:
        return redirect(url_for("index"))
    if request.method == "POST":
        email = request.form.get("email", "").strip().lower()
        u = q_one("SELECT * FROM users WHERE email=?", email)
        if u and check_password_hash(u["password_hash"], request.form.get("password", "")):
            if u["status"] != "aktif":
                flash("Akun dinonaktifkan. Hubungi petugas perpustakaan.", "error")
            else:
                session.clear()
                session["uid"] = u["id"]
                return redirect(url_for("index"))
        else:
            flash("Email atau kata sandi salah.", "error")
    return render_template("login.html")


@app.route("/daftar", methods=["GET", "POST"])
def register():
    if g.user:
        return redirect(url_for("index"))
    if request.method == "POST":
        f = request.form
        name = f.get("name", "").strip()
        email = f.get("email", "").strip().lower()
        pw = f.get("password", "")
        error = None
        if not name:
            error = "Nama lengkap wajib diisi."
        elif not EMAIL_RE.match(email):
            error = "Format email tidak valid."
        elif len(pw) < MIN_PASSWORD:
            error = f"Kata sandi minimal {MIN_PASSWORD} karakter."
        elif pw != f.get("confirm_password"):
            error = "Konfirmasi kata sandi tidak sama."
        elif q_one("SELECT 1 FROM users WHERE email=?", email):
            error = "Email sudah terdaftar. Silakan masuk."
        if error:
            flash(error, "error")
        else:
            cur = run("INSERT INTO users (name, email, password_hash, role, phone) "
                      "VALUES (?,?,?,'anggota',?)",
                      name, email, generate_password_hash(pw), f.get("phone", "").strip() or None)
            run("UPDATE users SET member_code=? WHERE id=?", f"AG{cur.lastrowid:04d}", cur.lastrowid)
            session.clear()
            session["uid"] = cur.lastrowid
            flash("Pendaftaran berhasil. Selamat datang di PUSTAKA!", "ok")
            return redirect(url_for("index"))
    return render_template("register.html")


@app.route("/keluar")
def logout():
    session.clear()
    return redirect(url_for("login"))


@app.route("/profil", methods=["GET", "POST"])
@login_required
def profile():
    if request.method == "POST":
        f = request.form
        if f.get("action") == "password":
            if not check_password_hash(g.user["password_hash"], f.get("current_password", "")):
                flash("Kata sandi lama salah.", "error")
            elif len(f.get("new_password", "")) < MIN_PASSWORD:
                flash(f"Kata sandi baru minimal {MIN_PASSWORD} karakter.", "error")
            elif f["new_password"] != f.get("confirm_password"):
                flash("Konfirmasi kata sandi tidak sama.", "error")
            else:
                run("UPDATE users SET password_hash=? WHERE id=?",
                    generate_password_hash(f["new_password"]), g.user["id"])
                flash("Kata sandi berhasil diganti.", "ok")
                return redirect(url_for("profile"))
        else:
            name = f.get("name", "").strip()
            if not name:
                flash("Nama tidak boleh kosong.", "error")
            else:
                run("UPDATE users SET name=?, phone=?, address=? WHERE id=?", name,
                    f.get("phone", "").strip() or None, f.get("address", "").strip() or None,
                    g.user["id"])
                flash("Profil diperbarui.", "ok")
                return redirect(url_for("profile"))
    return render_template("profile.html", active="profile")


# ====================================================================== aturan peminjaman
def check_loan_rules(member_id, book_id):
    """Cek syarat meminjam. Return pesan error, atau None kalau boleh."""
    member = q_one("SELECT * FROM users WHERE id=? AND role='anggota'", member_id)
    book = q_one("SELECT * FROM books WHERE id=?", book_id)
    if not member or member["status"] != "aktif":
        return "Akun anggota tidak aktif."
    if not book:
        return "Buku tidak ditemukan."
    if q_val("SELECT COUNT(*) FROM loans WHERE member_id=? AND book_id=? "
             "AND status IN ('menunggu','dipinjam')", member_id, book_id):
        return "Buku ini sedang dipinjam atau sudah diajukan."
    if book["stock_available"] < 1:
        return "Stok buku sedang habis."
    if q_val("SELECT COUNT(*) FROM loans WHERE member_id=? AND status='dipinjam' AND due_date < ?",
             member_id, today_str()):
        return "Ada buku yang terlambat dikembalikan. Kembalikan dulu sebelum meminjam lagi."
    if q_val("SELECT COUNT(*) FROM loans WHERE member_id=? AND status IN ('menunggu','dipinjam')",
             member_id) >= cfg("max_loans"):
        return f"Batas maksimal {cfg('max_loans')} buku sekaligus sudah tercapai."
    return None


def take_stock(book_id):
    """Kurangi stok satu eksemplar secara aman. Return True kalau berhasil."""
    cur = db().execute("UPDATE books SET stock_available = stock_available - 1 "
                       "WHERE id=? AND stock_available > 0", (book_id,))
    return cur.rowcount == 1


# ====================================================================== PETUGAS: dashboard
@app.route("/dashboard")
@staff_required
def dashboard():
    t = today_str()
    stats = {
        "books": q_val("SELECT COALESCE(SUM(stock_total),0) FROM books"),
        "members": q_val("SELECT COUNT(*) FROM users WHERE role='anggota' AND status='aktif'"),
        "borrowed": q_val("SELECT COUNT(*) FROM loans WHERE status='dipinjam'"),
        "late": q_val("SELECT COUNT(*) FROM loans WHERE status='dipinjam' AND due_date < ?", t),
        "pending": q_val("SELECT COUNT(*) FROM loans WHERE status='menunggu'"),
    }
    recent = decorate(q_all(LOAN_SELECT + " ORDER BY l.id DESC LIMIT 6"))
    need_return = decorate(q_all(LOAN_SELECT + " WHERE l.status='dipinjam' "
                                 "ORDER BY l.due_date ASC LIMIT 5"))
    return render_template("dashboard.html", stats=stats, recent=recent,
                           need_return=need_return, active="dashboard")


# ====================================================================== PETUGAS: buku
def parse_book_form():
    f = request.form
    try:
        total = int(f.get("stock_total") or 0)
        year = int(f["year"]) if f.get("year", "").strip() else None
        cat = int(f["category_id"]) if f.get("category_id") else None
    except ValueError:
        return None, "Tahun dan jumlah stok harus berupa angka."
    d = dict(isbn=f.get("isbn", "").strip() or None, title=f.get("title", "").strip(),
             author=f.get("author", "").strip(), publisher=f.get("publisher", "").strip() or None,
             year=year, category_id=cat, shelf=f.get("shelf", "").strip() or None,
             description=f.get("description", "").strip() or None, stock_total=total)
    if not d["title"] or not d["author"]:
        return None, "Judul dan penulis wajib diisi."
    if total < 0:
        return None, "Jumlah stok tidak boleh negatif."
    return d, None


@app.route("/buku")
@staff_required
def books():
    q = request.args.get("q", "").strip()
    cat = request.args.get("kategori", "")
    sql, args = BOOK_SELECT + " WHERE 1=1", []
    if q:
        sql += " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR c.name LIKE ?)"
        args += [f"%{q}%"] * 4
    if cat:
        sql += " AND b.category_id = ?"
        args.append(cat)
    rows = q_all(sql + " ORDER BY b.title", *args)
    return render_template("books.html", rows=rows, categories=categories(), q=q, cat=cat,
                           active="books")


@app.route("/buku/tambah", methods=["GET", "POST"])
@staff_required
def book_new():
    if request.method == "POST":
        d, err = parse_book_form()
        if err:
            flash(err, "error")
        else:
            try:
                run("INSERT INTO books (isbn,title,author,publisher,year,category_id,shelf,"
                    "description,stock_total,stock_available) VALUES (?,?,?,?,?,?,?,?,?,?)",
                    d["isbn"], d["title"], d["author"], d["publisher"], d["year"],
                    d["category_id"], d["shelf"], d["description"], d["stock_total"],
                    d["stock_total"])
                flash("Buku berhasil ditambahkan.", "ok")
                return redirect(url_for("books"))
            except sqlite3.IntegrityError:
                flash("ISBN sudah terdaftar.", "error")
    v = request.form if request.method == "POST" else {"stock_total": 1}
    return render_template("book_form.html", book=None, v=v, categories=categories(), active="books")


@app.route("/buku/<int:bid>/ubah", methods=["GET", "POST"])
@staff_required
def book_edit(bid):
    book = q_one("SELECT * FROM books WHERE id=?", bid)
    if not book:
        abort(404)
    if request.method == "POST":
        d, err = parse_book_form()
        borrowed = book["stock_total"] - book["stock_available"]
        if err:
            flash(err, "error")
        elif d["stock_total"] < borrowed:
            flash(f"Jumlah stok tidak boleh kurang dari {borrowed} (sedang dipinjam).", "error")
        else:
            try:
                run("UPDATE books SET isbn=?, title=?, author=?, publisher=?, year=?, category_id=?, "
                    "shelf=?, description=?, stock_total=?, stock_available=? WHERE id=?",
                    d["isbn"], d["title"], d["author"], d["publisher"], d["year"], d["category_id"],
                    d["shelf"], d["description"], d["stock_total"], d["stock_total"] - borrowed, bid)
                flash("Data buku diperbarui.", "ok")
                return redirect(url_for("books"))
            except sqlite3.IntegrityError:
                flash("ISBN sudah dipakai buku lain.", "error")
    v = request.form if request.method == "POST" else dict(book)
    return render_template("book_form.html", book=book, v=v, categories=categories(), active="books")


@app.route("/buku/<int:bid>/hapus", methods=["POST"])
@staff_required
def book_delete(bid):
    if q_val("SELECT COUNT(*) FROM loans WHERE book_id=?", bid):
        flash("Buku punya riwayat peminjaman, tidak bisa dihapus.", "error")
    else:
        run("DELETE FROM books WHERE id=?", bid)
        flash("Buku dihapus.", "ok")
    return redirect(url_for("books"))


# ====================================================================== PETUGAS: kategori
@app.route("/kategori")
@staff_required
def category_list():
    return render_template("categories.html", categories=categories(), active="categories")


@app.route("/kategori/tambah", methods=["POST"])
@staff_required
def category_add():
    name = request.form.get("name", "").strip()
    if not name:
        flash("Nama kategori wajib diisi.", "error")
    else:
        try:
            run("INSERT INTO categories (name) VALUES (?)", name)
            flash("Kategori ditambahkan.", "ok")
        except sqlite3.IntegrityError:
            flash("Kategori itu sudah ada.", "error")
    return redirect(url_for("category_list"))


@app.route("/kategori/<int:cid>/ubah", methods=["POST"])
@staff_required
def category_edit(cid):
    name = request.form.get("name", "").strip()
    if not name:
        flash("Nama kategori wajib diisi.", "error")
    else:
        try:
            run("UPDATE categories SET name=? WHERE id=?", name, cid)
            flash("Kategori diperbarui.", "ok")
        except sqlite3.IntegrityError:
            flash("Kategori itu sudah ada.", "error")
    return redirect(url_for("category_list"))


@app.route("/kategori/<int:cid>/hapus", methods=["POST"])
@staff_required
def category_delete(cid):
    if q_val("SELECT COUNT(*) FROM books WHERE category_id=?", cid):
        flash("Kategori masih dipakai buku, tidak bisa dihapus.", "error")
    else:
        run("DELETE FROM categories WHERE id=?", cid)
        flash("Kategori dihapus.", "ok")
    return redirect(url_for("category_list"))


# ====================================================================== PETUGAS: anggota & petugas
def manage_user(kind, uid=None):
    """Form tambah/ubah akun. kind = 'anggota' (petugas boleh) atau 'staff' (khusus admin)."""
    user = None
    if uid:
        user = q_one("SELECT * FROM users WHERE id=?", uid)
        if not user or (kind == "anggota") != (user["role"] == "anggota"):
            abort(404)
    if request.method == "POST":
        f = request.form
        name = f.get("name", "").strip()
        email = f.get("email", "").strip().lower()
        pw = f.get("password", "")
        status = f.get("status", "aktif") if user else "aktif"
        if status not in ("aktif", "nonaktif"):
            status = "aktif"
        role = "anggota" if kind == "anggota" else (
            f.get("role") if f.get("role") in STAFF_ROLES else "petugas")
        error = None
        if not name:
            error = "Nama wajib diisi."
        elif not EMAIL_RE.match(email):
            error = "Format email tidak valid."
        elif q_one("SELECT 1 FROM users WHERE email=? AND id<>?", email, uid or 0):
            error = "Email sudah dipakai akun lain."
        elif not user and len(pw) < MIN_PASSWORD:
            error = f"Kata sandi minimal {MIN_PASSWORD} karakter."
        elif pw and len(pw) < MIN_PASSWORD:
            error = f"Kata sandi minimal {MIN_PASSWORD} karakter."
        elif user and user["id"] == g.user["id"] and status == "nonaktif":
            error = "Kamu tidak bisa menonaktifkan akunmu sendiri."
        elif (user and user["role"] == "admin" and user["status"] == "aktif"
              and (role != "admin" or status != "aktif")
              and not q_val("SELECT COUNT(*) FROM users WHERE role='admin' AND status='aktif' "
                            "AND id<>?", user["id"])):
            error = "Tidak bisa menurunkan atau menonaktifkan admin terakhir."
        if error:
            flash(error, "error")
        else:
            phone = f.get("phone", "").strip() or None
            address = f.get("address", "").strip() or None
            if user:
                run("UPDATE users SET name=?, email=?, role=?, phone=?, address=?, status=? "
                    "WHERE id=?", name, email, role, phone, address, status, uid)
                if pw:
                    run("UPDATE users SET password_hash=? WHERE id=?",
                        generate_password_hash(pw), uid)
                flash("Data akun diperbarui.", "ok")
            else:
                cur = run("INSERT INTO users (name, email, password_hash, role, phone, address) "
                          "VALUES (?,?,?,?,?,?)", name, email, generate_password_hash(pw), role,
                          phone, address)
                if role == "anggota":
                    run("UPDATE users SET member_code=? WHERE id=?",
                        f"AG{cur.lastrowid:04d}", cur.lastrowid)
                flash("Akun berhasil dibuat.", "ok")
            return redirect(url_for("members" if kind == "anggota" else "staff_list"))
    v = request.form if request.method == "POST" else (dict(user) if user else {"status": "aktif"})
    return render_template("user_form.html", kind=kind, user=user, v=v,
                           active="members" if kind == "anggota" else "staff")


@app.route("/anggota")
@staff_required
def members():
    q = request.args.get("q", "").strip()
    st = request.args.get("status", "")
    sql = ("SELECT u.*, (SELECT COUNT(*) FROM loans l WHERE l.member_id=u.id AND l.status='dipinjam') "
           "AS borrowed FROM users u WHERE u.role='anggota'")
    args = []
    if q:
        sql += " AND (u.name LIKE ? OR u.email LIKE ? OR u.member_code LIKE ? OR u.phone LIKE ?)"
        args += [f"%{q}%"] * 4
    if st in ("aktif", "nonaktif"):
        sql += " AND u.status=?"
        args.append(st)
    return render_template("users.html", kind="anggota", rows=q_all(sql + " ORDER BY u.name", *args),
                           q=q, st=st, active="members")


@app.route("/anggota/tambah", methods=["GET", "POST"])
@staff_required
def member_new():
    return manage_user("anggota")


@app.route("/anggota/<int:uid>/ubah", methods=["GET", "POST"])
@staff_required
def member_edit(uid):
    return manage_user("anggota", uid)


@app.route("/pengguna")
@admin_required
def staff_list():
    rows = q_all("SELECT * FROM users WHERE role IN ('admin','petugas') ORDER BY role, name")
    return render_template("users.html", kind="staff", rows=rows, q="", st="", active="staff")


@app.route("/pengguna/tambah", methods=["GET", "POST"])
@admin_required
def staff_new():
    return manage_user("staff")


@app.route("/pengguna/<int:uid>/ubah", methods=["GET", "POST"])
@admin_required
def staff_edit(uid):
    return manage_user("staff", uid)


# ====================================================================== PETUGAS: peminjaman
@app.route("/peminjaman")
@staff_required
def loans():
    status = request.args.get("status", "semua")
    q = request.args.get("q", "").strip()
    sql, args = LOAN_SELECT + " WHERE 1=1", []
    if status == "terlambat":
        sql += " AND l.status='dipinjam' AND l.due_date < ?"
        args.append(today_str())
    elif status in ("menunggu", "dipinjam", "dikembalikan", "ditolak"):
        sql += " AND l.status=?"
        args.append(status)
    if q:
        m = re.fullmatch(r"(?i)\s*PJ-?0*(\d+)\s*", q)
        sql += " AND (u.name LIKE ? OR u.member_code LIKE ? OR b.title LIKE ? OR l.id=?)"
        args += [f"%{q}%", f"%{q}%", f"%{q}%", int(m.group(1)) if m else -1]
    rows = decorate(q_all(sql + " ORDER BY l.id DESC LIMIT 300", *args))
    counts = {
        "menunggu": q_val("SELECT COUNT(*) FROM loans WHERE status='menunggu'"),
        "dipinjam": q_val("SELECT COUNT(*) FROM loans WHERE status='dipinjam'"),
        "terlambat": q_val("SELECT COUNT(*) FROM loans WHERE status='dipinjam' AND due_date < ?",
                           today_str()),
    }
    return render_template("loans.html", rows=rows, status=status, q=q, counts=counts,
                           active="loans")


@app.route("/peminjaman/baru", methods=["GET", "POST"])
@staff_required
def loan_new():
    """Petugas mencatat peminjaman langsung di meja layanan (tanpa pengajuan)."""
    if request.method == "POST":
        try:
            member_id, book_id = int(request.form["member_id"]), int(request.form["book_id"])
        except (KeyError, ValueError):
            flash("Pilih anggota dan buku.", "error")
            return redirect(url_for("loan_new"))
        err = check_loan_rules(member_id, book_id)
        if err:
            flash(err, "error")
        elif not take_stock(book_id):
            flash("Stok buku sedang habis.", "error")
        else:
            due = date.today() + timedelta(days=cfg("loan_days"))
            db().execute("INSERT INTO loans (book_id, member_id, status, requested_at, loan_date, "
                         "due_date, processed_by) VALUES (?,?,'dipinjam',?,?,?,?)",
                         (book_id, member_id, today_str(), today_str(), due.isoformat(), g.user["id"]))
            db().commit()
            flash(f"Peminjaman dicatat. Jatuh tempo {f_tgl(due.isoformat())}.", "ok")
            return redirect(url_for("loans", status="dipinjam"))
    members_ = q_all("SELECT id, name, member_code FROM users WHERE role='anggota' "
                     "AND status='aktif' ORDER BY name")
    books_ = q_all("SELECT id, title, author, stock_available FROM books "
                   "WHERE stock_available > 0 ORDER BY title")
    return render_template("loan_new.html", members=members_, books=books_, active="loans")


@app.route("/peminjaman/<int:lid>/setujui", methods=["POST"])
@staff_required
def loan_approve(lid):
    loan = q_one("SELECT * FROM loans WHERE id=? AND status='menunggu'", lid)
    member = q_one("SELECT * FROM users WHERE id=?", loan["member_id"]) if loan else None
    if not loan:
        flash("Pengajuan tidak ditemukan atau sudah diproses.", "error")
    elif member["status"] != "aktif":
        flash("Akun anggota tidak aktif.", "error")
    elif q_val("SELECT COUNT(*) FROM loans WHERE member_id=? AND status='dipinjam' AND due_date < ?",
               loan["member_id"], today_str()):
        flash("Anggota masih punya buku terlambat. Pengajuan sebaiknya ditolak.", "error")
    elif not take_stock(loan["book_id"]):
        flash("Stok buku habis, pengajuan belum bisa disetujui.", "error")
    else:
        due = date.today() + timedelta(days=cfg("loan_days"))
        db().execute("UPDATE loans SET status='dipinjam', loan_date=?, due_date=?, processed_by=? "
                     "WHERE id=?", (today_str(), due.isoformat(), g.user["id"], lid))
        db().commit()
        flash(f"Pengajuan disetujui. Jatuh tempo {f_tgl(due.isoformat())}.", "ok")
    return redirect(request.form.get("next") or url_for("loans", status="menunggu"))


@app.route("/peminjaman/<int:lid>/tolak", methods=["POST"])
@staff_required
def loan_reject(lid):
    cur = run("UPDATE loans SET status='ditolak', processed_by=? WHERE id=? AND status='menunggu'",
              g.user["id"], lid)
    flash("Pengajuan ditolak." if cur.rowcount else "Pengajuan tidak ditemukan atau sudah diproses.",
          "ok" if cur.rowcount else "error")
    return redirect(url_for("loans", status="menunggu"))


# ====================================================================== PETUGAS: pengembalian
@app.route("/pengembalian")
@staff_required
def returns():
    q = request.args.get("q", "").strip()
    lid = request.args.get("id", type=int)
    selected, matches = None, []
    if lid:
        rows = q_all(LOAN_SELECT + " WHERE l.id=? AND l.status='dipinjam'", lid)
        selected = decorate(rows)[0] if rows else None
    elif q:
        m = re.fullmatch(r"(?i)\s*PJ-?0*(\d+)\s*", q)
        rows = decorate(q_all(
            LOAN_SELECT + " WHERE l.status='dipinjam' AND (l.id=? OR u.name LIKE ? OR "
            "u.member_code LIKE ? OR b.title LIKE ?) ORDER BY l.due_date",
            int(m.group(1)) if m else -1, f"%{q}%", f"%{q}%", f"%{q}%"))
        if len(rows) == 1:
            selected = rows[0]
        else:
            matches = rows
    waiting = decorate(q_all(LOAN_SELECT + " WHERE l.status='dipinjam' "
                             "ORDER BY l.due_date ASC LIMIT 8"))
    return render_template("returns.html", q=q, selected=selected, matches=matches,
                           waiting=waiting, today=today_str(), active="returns")


@app.route("/pengembalian/<int:lid>", methods=["POST"])
@staff_required
def return_process(lid):
    rows = q_all(LOAN_SELECT + " WHERE l.id=? AND l.status='dipinjam'", lid)
    if not rows:
        flash("Data peminjaman tidak ditemukan atau sudah dikembalikan.", "error")
        return redirect(url_for("returns"))
    loan = decorate(rows)[0]
    db().execute("UPDATE loans SET status='dikembalikan', return_date=?, fine=?, returned_by=? "
                 "WHERE id=?", (today_str(), loan["est_fine"], g.user["id"], lid))
    db().execute("UPDATE books SET stock_available = stock_available + 1 WHERE id=?",
                 (loan["book_id"],))
    db().commit()
    if loan["est_fine"]:
        flash(f"Buku \"{loan['title']}\" dikembalikan. Terlambat {loan['late_days']} hari, "
              f"denda {f_rupiah(loan['est_fine'])}.", "ok")
    else:
        flash(f"Buku \"{loan['title']}\" dikembalikan tepat waktu.", "ok")
    return redirect(url_for("returns"))


# ====================================================================== PETUGAS: laporan
def report_data():
    first = date.today().replace(day=1)
    dari = parse_date(request.args.get("dari"), first)
    sampai = parse_date(request.args.get("sampai"), date.today())
    if dari > sampai:
        dari, sampai = sampai, dari
    rows = decorate(q_all(
        LOAN_SELECT + " WHERE l.status IN ('dipinjam','dikembalikan') AND l.loan_date BETWEEN ? AND ? "
        "ORDER BY l.loan_date DESC, l.id DESC", dari.isoformat(), sampai.isoformat()))
    return dari, sampai, rows


@app.route("/laporan")
@staff_required
def report():
    dari, sampai, rows = report_data()
    summary = {
        "total": len(rows),
        "borrowed": sum(1 for r in rows if r["status"] == "dipinjam"),
        "returned": sum(1 for r in rows if r["status"] == "dikembalikan"),
        "fine": sum(r["shown_fine"] for r in rows),
    }
    counter = {}
    for r in rows:
        counter[r["title"]] = counter.get(r["title"], 0) + 1
    top = sorted(counter.items(), key=lambda x: (-x[1], x[0]))[:5]
    return render_template("report.html", rows=rows, summary=summary, top=top,
                           dari=dari.isoformat(), sampai=sampai.isoformat(), active="report")


@app.route("/laporan/csv")
@staff_required
def report_csv():
    dari, sampai, rows = report_data()
    buf = io.StringIO()
    w = csv.writer(buf)
    w.writerow(["Kode", "Anggota", "Kode Anggota", "Buku", "Tgl Pinjam", "Jatuh Tempo",
                "Tgl Kembali", "Status", "Denda (Rp)"])
    for r in rows:
        w.writerow([r["code"], r["member_name"], r["member_code"], r["title"], r["loan_date"],
                    r["due_date"], r["return_date"] or "", r["state"], r["shown_fine"]])
    data = "﻿" + buf.getvalue()  # BOM supaya Excel membaca UTF-8
    return Response(data, mimetype="text/csv; charset=utf-8", headers={
        "Content-Disposition": f"attachment; filename=laporan-peminjaman_{dari}_{sampai}.csv"})


# ====================================================================== ADMIN: pengaturan
@app.route("/pengaturan", methods=["GET", "POST"])
@admin_required
def settings_page():
    if request.method == "POST":
        try:
            vals = {k: int(request.form[k]) for k in DEFAULTS}
            if vals["loan_days"] < 1 or vals["max_loans"] < 1 or vals["fine_per_day"] < 0:
                raise ValueError
        except (KeyError, ValueError):
            flash("Isi semua kolom dengan angka yang valid.", "error")
        else:
            for k, v in vals.items():
                db().execute("UPDATE settings SET value=? WHERE key=?", (str(v), k))
            db().commit()
            flash("Pengaturan disimpan.", "ok")
            return redirect(url_for("settings_page"))
    return render_template("settings.html", active="settings")


# ====================================================================== ANGGOTA: katalog
def fav_ids(member_id):
    return {r[0] for r in q_all("SELECT book_id FROM favorites WHERE member_id=?", member_id)}


def recommendations(member_id, limit=6):
    """Buku sekategori dengan yang pernah dipinjam/difavoritkan, lalu isi sisanya dengan yang populer."""
    cat_ids = [r[0] for r in q_all(
        "SELECT DISTINCT b.category_id FROM books b WHERE b.category_id IS NOT NULL AND "
        "(b.id IN (SELECT book_id FROM loans WHERE member_id=?) OR "
        " b.id IN (SELECT book_id FROM favorites WHERE member_id=?))", member_id, member_id)]
    skip = "b.id NOT IN (SELECT book_id FROM loans WHERE member_id=? AND status IN ('menunggu','dipinjam'))"
    popular = "ORDER BY (SELECT COUNT(*) FROM loans x WHERE x.book_id=b.id) DESC, b.id DESC"
    picked = []
    if cat_ids:
        marks = ",".join("?" * len(cat_ids))
        picked = q_all(f"{BOOK_SELECT} WHERE {skip} AND b.category_id IN ({marks}) {popular} LIMIT ?",
                       member_id, *cat_ids, limit)
    if len(picked) < limit:
        have = [r["id"] for r in picked] or [0]
        marks = ",".join("?" * len(have))
        picked = list(picked) + list(q_all(
            f"{BOOK_SELECT} WHERE {skip} AND b.id NOT IN ({marks}) {popular} LIMIT ?",
            member_id, *have, limit - len(picked)))
    return picked


@app.route("/katalog")
@member_required
def catalog():
    q = request.args.get("q", "").strip()
    cat = request.args.get("kategori", type=int)
    urut = request.args.get("urut", "")
    mid = g.user["id"]
    my_active = decorate(q_all(LOAN_SELECT + " WHERE l.member_id=? AND l.status IN "
                               "('menunggu','dipinjam') ORDER BY l.id DESC", mid))
    ctx = dict(my_active=my_active, favs=fav_ids(mid), q=q, cat=cat, categories=categories())
    if q or cat:
        sql, args = BOOK_SELECT + " WHERE 1=1", []
        if q:
            sql += " AND (b.title LIKE ? OR b.author LIKE ? OR b.publisher LIKE ? OR c.name LIKE ?)"
            args += [f"%{q}%"] * 4
        if cat:
            sql += " AND b.category_id=?"
            args.append(cat)
        title = "Hasil Pencarian" if q else "Kategori: " + (
            q_val("SELECT name FROM categories WHERE id=?", cat) if q_one(
                "SELECT 1 FROM categories WHERE id=?", cat) else "-")
        return render_template("catalog.html", mode="list", heading=title,
                               results=q_all(sql + " ORDER BY b.title", *args), active="catalog", **ctx)
    if urut == "terbaru":
        return render_template("catalog.html", mode="list", heading="Buku Terkini",
                               results=q_all(BOOK_SELECT + " ORDER BY b.id DESC"),
                               active="latest", **ctx)
    return render_template("catalog.html", mode="home", active="catalog",
                           new_books=q_all(BOOK_SELECT + " ORDER BY b.id DESC LIMIT 6"),
                           recs=recommendations(mid, 6), **ctx)


@app.route("/katalog/kategori")
@member_required
def catalog_categories():
    return render_template("catalog_categories.html", categories=categories(), active="cats")


@app.route("/katalog/<int:bid>")
@login_required
def book_detail(bid):
    book = q_one(BOOK_SELECT + " WHERE b.id=?", bid)
    if not book:
        abort(404)
    info = dict(book=book, reason=None, is_fav=False, my_stars=None, can_rate=False, active_loan=None)
    if g.user["role"] == "anggota":
        mid = g.user["id"]
        info["reason"] = check_loan_rules(mid, bid)
        info["is_fav"] = bid in fav_ids(mid)
        r = q_one("SELECT stars FROM ratings WHERE member_id=? AND book_id=?", mid, bid)
        info["my_stars"] = r["stars"] if r else None
        info["can_rate"] = bool(q_val("SELECT COUNT(*) FROM loans WHERE member_id=? AND book_id=? "
                                      "AND status IN ('dipinjam','dikembalikan')", mid, bid))
        al = q_all(LOAN_SELECT + " WHERE l.member_id=? AND l.book_id=? AND l.status IN "
                   "('menunggu','dipinjam')", mid, bid)
        info["active_loan"] = decorate(al)[0] if al else None
    return render_template("book_detail.html", active="catalog", **info)


@app.route("/katalog/<int:bid>/ajukan", methods=["POST"])
@member_required
def loan_request(bid):
    err = check_loan_rules(g.user["id"], bid)
    if err:
        flash(err, "error")
        return redirect(url_for("book_detail", bid=bid))
    run("INSERT INTO loans (book_id, member_id, status, requested_at) VALUES (?,?,'menunggu',?)",
        bid, g.user["id"], today_str())
    flash("Pengajuan terkirim. Tunggu persetujuan petugas, lalu ambil bukunya di perpustakaan.", "ok")
    return redirect(url_for("my_loans"))


@app.route("/katalog/<int:bid>/favorit", methods=["POST"])
@member_required
def favorite_toggle(bid):
    if not q_one("SELECT 1 FROM books WHERE id=?", bid):
        abort(404)
    if q_one("SELECT 1 FROM favorites WHERE member_id=? AND book_id=?", g.user["id"], bid):
        run("DELETE FROM favorites WHERE member_id=? AND book_id=?", g.user["id"], bid)
        flash("Dihapus dari favorit.", "ok")
    else:
        run("INSERT INTO favorites (member_id, book_id) VALUES (?,?)", g.user["id"], bid)
        flash("Ditambahkan ke favorit.", "ok")
    if request.form.get("back") == "favorit":
        return redirect(url_for("favorites"))
    return redirect(url_for("book_detail", bid=bid))


@app.route("/katalog/<int:bid>/rating", methods=["POST"])
@member_required
def rate_book(bid):
    stars = request.form.get("stars", type=int)
    if not stars or not 1 <= stars <= 5:
        flash("Pilih rating 1 sampai 5.", "error")
    elif not q_val("SELECT COUNT(*) FROM loans WHERE member_id=? AND book_id=? "
                   "AND status IN ('dipinjam','dikembalikan')", g.user["id"], bid):
        flash("Rating hanya bisa diberikan untuk buku yang pernah kamu pinjam.", "error")
    else:
        run("INSERT INTO ratings (member_id, book_id, stars) VALUES (?,?,?) "
            "ON CONFLICT(member_id, book_id) DO UPDATE SET stars=excluded.stars",
            g.user["id"], bid, stars)
        flash("Terima kasih, rating tersimpan.", "ok")
    return redirect(url_for("book_detail", bid=bid))


@app.route("/pinjaman-saya")
@member_required
def my_loans():
    tab = "riwayat" if request.args.get("tab") == "riwayat" else "aktif"
    states = "('menunggu','dipinjam')" if tab == "aktif" else "('dikembalikan','ditolak','dibatalkan')"
    rows = decorate(q_all(LOAN_SELECT + f" WHERE l.member_id=? AND l.status IN {states} "
                          "ORDER BY l.id DESC", g.user["id"]))
    total_fine = q_val("SELECT COALESCE(SUM(fine),0) FROM loans WHERE member_id=?", g.user["id"])
    return render_template("my_loans.html", rows=rows, tab=tab, total_fine=total_fine,
                           active="history" if tab == "riwayat" else "mine")


@app.route("/pinjaman-saya/<int:lid>/batal", methods=["POST"])
@member_required
def loan_cancel(lid):
    cur = run("UPDATE loans SET status='dibatalkan' WHERE id=? AND member_id=? AND status='menunggu'",
              lid, g.user["id"])
    flash("Pengajuan dibatalkan." if cur.rowcount else "Pengajuan tidak ditemukan.",
          "ok" if cur.rowcount else "error")
    return redirect(url_for("my_loans"))


@app.route("/favorit")
@member_required
def favorites():
    rows = q_all(BOOK_SELECT + " WHERE b.id IN (SELECT book_id FROM favorites WHERE member_id=?) "
                 "ORDER BY b.title", g.user["id"])
    return render_template("favorites.html", rows=rows, active="favs")


init_db()

if __name__ == "__main__":
    app.run(debug=True, port=int(os.environ.get("PORT", 5050)))
