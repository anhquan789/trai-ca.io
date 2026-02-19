<?php
/**
 * Trại Cá Sạch — Admin Panel
 * Simple PHP admin for uploading images and managing posts.
 * ⚠️  This file requires a PHP server (not GitHub Pages).
 *     Place on any PHP host (cPanel, VPS, shared hosting, etc.).
 */

session_start();

// ── Configuration ──────────────────────────────────────────────
define('ADMIN_USER',    'admin');
// ⚠️  SECURITY: You MUST change this password before deploying to production!
//     Generate a new hash: php -r "echo password_hash('YourNewPassword', PASSWORD_DEFAULT);"
//     Then replace the password_hash() call below with the generated string.
define('ADMIN_PASS_HASH', password_hash('traica2025', PASSWORD_DEFAULT));
define('DATE_FORMAT',   'd/m/Y');
define('POSTS_FILE',   __DIR__ . '/posts.json');
define('UPLOAD_DIR',   dirname(__DIR__) . '/images/');
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('MAX_SIZE',      10 * 1024 * 1024); // 10 MB
// ───────────────────────────────────────────────────────────────

$action  = $_GET['action'] ?? 'dashboard';
$message = '';
$msgType = '';

/* ---- Helpers ---- */
function loadPosts(): array {
    if (!file_exists(POSTS_FILE)) { return []; }
    $data = json_decode(file_get_contents(POSTS_FILE), true);
    return is_array($data) ? $data : [];
}

function savePosts(array $posts): void {
    file_put_contents(POSTS_FILE, json_encode(array_values($posts), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function isLoggedIn(): bool {
    return !empty($_SESSION['admin_logged_in']);
}

/* ---- Login / Logout ---- */
if ($action === 'logout') {
    session_destroy();
    redirect('admin.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($user === ADMIN_USER && password_verify($pass, ADMIN_PASS_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        redirect('admin.php');
    } else {
        $message = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        $msgType = 'error';
    }
}

/* ---- Guard ---- */
if (!isLoggedIn() && $action !== 'login') {
    $action = 'login';
}

/* ---- Upload Image ---- */
if (isLoggedIn() && $action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['photo']['tmp_name'])) {
        $file = $_FILES['photo'];
        $mime = mime_content_type($file['tmp_name']);

        if (!in_array($mime, ALLOWED_TYPES, true)) {
            $message = 'Định dạng file không được hỗ trợ. Vui lòng chọn JPG, PNG, GIF hoặc WebP.';
            $msgType = 'error';
        } elseif ($file['size'] > MAX_SIZE) {
            $message = 'File quá lớn (tối đa 10 MB).';
            $msgType = 'error';
        } else {
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'upload_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest     = UPLOAD_DIR . $filename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $message = 'Tải ảnh lên thành công: ' . e($filename);
                $msgType = 'success';
                $action  = 'dashboard';
            } else {
                $message = 'Lỗi khi lưu file. Kiểm tra quyền ghi vào thư mục images/.';
                $msgType = 'error';
            }
        }
    } else {
        $message = 'Vui lòng chọn file ảnh.';
        $msgType = 'error';
    }
}

/* ---- Create / Edit Post ---- */
if (isLoggedIn() && $action === 'save_post' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $posts = loadPosts();
    $id    = trim($_POST['id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $cat   = trim($_POST['category'] ?? '');
    $body  = trim($_POST['body'] ?? '');
    $img   = trim($_POST['image'] ?? '');
    $date  = date(DATE_FORMAT);

    if ($title === '' || $body === '') {
        $message = 'Tiêu đề và nội dung không được để trống.';
        $msgType = 'error';
        $action  = 'new_post';
    } else {
        if ($id !== '') {
            // Update existing
            foreach ($posts as &$p) {
                if ($p['id'] === $id) {
                    $p['title']    = $title;
                    $p['category'] = $cat;
                    $p['body']     = $body;
                    $p['image']    = $img;
                    break;
                }
            }
            unset($p);
            $message = 'Đã cập nhật bài viết.';
        } else {
            // New post
            $posts[] = [
                'id'       => uniqid('post_', true),
                'title'    => $title,
                'category' => $cat,
                'body'     => $body,
                'image'    => $img,
                'date'     => $date,
            ];
            $message = 'Đã đăng bài viết mới!';
        }
        $msgType = 'success';
        savePosts($posts);
        $action = 'dashboard';
    }
}

/* ---- Delete Post ---- */
if (isLoggedIn() && $action === 'delete_post' && isset($_GET['id'])) {
    $posts = loadPosts();
    $posts = array_filter($posts, fn($p) => $p['id'] !== $_GET['id']);
    savePosts($posts);
    redirect('admin.php');
}

/* ---- List images ---- */
$images = [];
if (isLoggedIn() && is_dir(UPLOAD_DIR)) {
    foreach (glob(UPLOAD_DIR . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) as $f) {
        $images[] = basename($f);
    }
    sort($images);
}

$posts = isLoggedIn() ? loadPosts() : [];

/* ---- Edit post lookup ---- */
$editPost = null;
if ($action === 'edit_post' && isset($_GET['id'])) {
    foreach ($posts as $p) {
        if ($p['id'] === $_GET['id']) { $editPost = $p; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Trang Quản Trị – Trại Cá Sạch</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --blue: #1a6fa8; --blue-d: #134f7a; --blue-l: #e8f4fd;
      --green: #2d8a5e; --red: #dc2626;
      --text: #1a1a2e; --muted: #6b7280; --border: #e5e7eb;
      --bg: #f8fafc; --white: #fff;
      --radius: 10px; --shadow: 0 2px 12px rgba(0,0,0,.07);
    }
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
    a { color: var(--blue); text-decoration: none; }
    a:hover { text-decoration: underline; }

    /* Sidebar */
    .layout { display: flex; min-height: 100vh; }
    .sidebar {
      width: 240px; background: #0a1628; color: #fff;
      display: flex; flex-direction: column; flex-shrink: 0;
    }
    .sidebar-brand {
      padding: 1.5rem 1.25rem 1rem;
      font-size: 1.15rem; font-weight: 700;
      border-bottom: 1px solid rgba(255,255,255,.1);
    }
    .sidebar-brand span { font-size: 1.4rem; }
    .sidebar-nav { padding: 1rem 0; flex: 1; }
    .sidebar-nav a {
      display: flex; align-items: center; gap: .65rem;
      padding: .7rem 1.25rem; color: rgba(255,255,255,.75);
      font-size: .92rem; transition: background .2s;
    }
    .sidebar-nav a:hover, .sidebar-nav a.active {
      background: rgba(255,255,255,.08); color: #fff; text-decoration: none;
    }
    .sidebar-footer { padding: 1rem 1.25rem; border-top: 1px solid rgba(255,255,255,.1); }
    .sidebar-footer a { color: rgba(255,255,255,.6); font-size: .85rem; }

    /* Main */
    .main { flex: 1; overflow: auto; }
    .topbar {
      background: var(--white); border-bottom: 1px solid var(--border);
      padding: .9rem 1.5rem; display: flex; align-items: center;
      justify-content: space-between; position: sticky; top: 0; z-index: 10;
    }
    .topbar h1 { font-size: 1.15rem; font-weight: 700; }
    .content { padding: 1.5rem; }

    /* Cards */
    .card {
      background: var(--white); border-radius: var(--radius);
      box-shadow: var(--shadow); overflow: hidden; margin-bottom: 1.5rem;
    }
    .card-header {
      padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);
      font-weight: 700; font-size: .95rem; display: flex; align-items: center;
      justify-content: space-between;
    }
    .card-body { padding: 1.25rem; }

    /* Stats */
    .stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin-bottom: 1.5rem; }
    .stat-box {
      background: var(--white); border-radius: var(--radius);
      box-shadow: var(--shadow); padding: 1.25rem;
      display: flex; align-items: center; gap: 1rem;
    }
    .stat-icon { font-size: 2rem; }
    .stat-num { font-size: 1.6rem; font-weight: 800; color: var(--blue); }
    .stat-label { font-size: .8rem; color: var(--muted); }

    /* Form */
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-weight: 600; font-size: .88rem; margin-bottom: .35rem; }
    .form-group input, .form-group textarea, .form-group select {
      width: 100%; padding: .6rem .9rem; border: 1.5px solid var(--border);
      border-radius: var(--radius); font-family: inherit; font-size: .9rem; color: var(--text);
      background: var(--white); outline: none; transition: border .2s;
      resize: vertical;
    }
    .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
      border-color: var(--blue); box-shadow: 0 0 0 3px rgba(26,111,168,.1);
    }

    /* Buttons */
    .btn { display: inline-flex; align-items: center; gap: .4rem; padding: .55rem 1.2rem;
      border-radius: 8px; font-family: inherit; font-size: .88rem; font-weight: 700;
      cursor: pointer; border: none; transition: all .2s; }
    .btn-primary { background: var(--blue); color: #fff; }
    .btn-primary:hover { background: var(--blue-d); }
    .btn-success { background: var(--green); color: #fff; }
    .btn-danger  { background: var(--red); color: #fff; }
    .btn-sm      { padding: .35rem .75rem; font-size: .8rem; }

    /* Table */
    table { width: 100%; border-collapse: collapse; font-size: .88rem; }
    th { background: var(--bg); padding: .65rem 1rem; text-align: left; font-weight: 700; color: var(--muted); font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; }
    td { padding: .65rem 1rem; border-bottom: 1px solid var(--border); }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: var(--bg); }
    .td-actions { display: flex; gap: .5rem; }

    /* Message */
    .alert { padding: .75rem 1rem; border-radius: var(--radius); margin-bottom: 1rem; font-size: .9rem; }
    .alert-success { background: #dcfce7; color: #166534; }
    .alert-error   { background: #fee2e2; color: #991b1b; }

    /* Image grid */
    .img-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(130px,1fr)); gap: .75rem; }
    .img-thumb { position: relative; border-radius: 8px; overflow: hidden; aspect-ratio: 1; background: var(--bg); }
    .img-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .img-name { font-size: .7rem; color: var(--muted); text-align: center; padding: .3rem .4rem; word-break: break-all; }

    /* Login page */
    .login-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg,#0a1628,#1a6fa8); }
    .login-box { background: var(--white); border-radius: 16px; padding: 2.5rem 2rem; width: 360px; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
    .login-box h2 { font-size: 1.4rem; margin-bottom: 1.5rem; text-align: center; }

    @media(max-width:768px){
      .sidebar { display: none; }
      .stats-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<?php if (!isLoggedIn()): ?>
<!-- ===== LOGIN PAGE ===== -->
<div class="login-page">
  <div class="login-box">
    <h2>🐟 Trại Cá Sạch<br /><small style="font-size:.7em;color:var(--muted)">Trang Quản Trị</small></h2>
    <?php if ($message): ?>
      <div class="alert alert-error"><?= e($message) ?></div>
    <?php endif; ?>
    <form method="POST" action="admin.php">
      <div class="form-group">
        <label>Tên đăng nhập</label>
        <input type="text" name="username" required autofocus />
      </div>
      <div class="form-group">
        <label>Mật khẩu</label>
        <input type="password" name="password" required />
      </div>
      <button type="submit" name="login" class="btn btn-primary" style="width:100%;justify-content:center;">Đăng Nhập</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ===== ADMIN LAYOUT ===== -->
<div class="layout">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-brand"><span>🐟</span> Trại Cá Sạch</div>
    <nav class="sidebar-nav">
      <a href="admin.php" class="<?= ($action === 'dashboard') ? 'active' : '' ?>">📊 Bảng Điều Khiển</a>
      <a href="admin.php?action=upload" class="<?= ($action === 'upload') ? 'active' : '' ?>">📷 Tải Ảnh Lên</a>
      <a href="admin.php?action=images" class="<?= ($action === 'images') ? 'active' : '' ?>">🖼️ Quản Lý Ảnh</a>
      <a href="admin.php?action=posts" class="<?= ($action === 'posts') ? 'active' : '' ?>">📝 Bài Viết</a>
      <a href="admin.php?action=new_post" class="<?= in_array($action, ['new_post','edit_post']) ? 'active' : '' ?>">✏️ Viết Bài Mới</a>
      <a href="../index.html" target="_blank">🌐 Xem Trang Web</a>
    </nav>
    <div class="sidebar-footer">
      <a href="admin.php?action=logout">🚪 Đăng Xuất</a>
    </div>
  </aside>

  <!-- Main -->
  <div class="main">
    <div class="topbar">
      <h1>
        <?php $titles = [
          'dashboard' => '📊 Bảng Điều Khiển',
          'upload'    => '📷 Tải Ảnh Lên',
          'images'    => '🖼️ Quản Lý Ảnh',
          'posts'     => '📝 Danh Sách Bài Viết',
          'new_post'  => '✏️ Viết Bài Mới',
          'edit_post' => '✏️ Chỉnh Sửa Bài',
        ]; echo $titles[$action] ?? ''; ?>
      </h1>
      <span style="color:var(--muted);font-size:.85rem">Xin chào, <strong>admin</strong></span>
    </div>

    <div class="content">

      <?php if ($message): ?>
        <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'error' ?>"><?= e($message) ?></div>
      <?php endif; ?>

      <?php /* ========== DASHBOARD ========== */ ?>
      <?php if ($action === 'dashboard'): ?>
        <div class="stats-row">
          <div class="stat-box">
            <div class="stat-icon">🖼️</div>
            <div><div class="stat-num"><?= count($images) ?></div><div class="stat-label">Hình Ảnh</div></div>
          </div>
          <div class="stat-box">
            <div class="stat-icon">📝</div>
            <div><div class="stat-num"><?= count($posts) ?></div><div class="stat-label">Bài Viết</div></div>
          </div>
          <div class="stat-box">
            <div class="stat-icon">🌐</div>
            <div><div class="stat-num"><a href="../index.html" target="_blank" style="font-size:1rem">Xem →</a></div><div class="stat-label">Trang Web</div></div>
          </div>
        </div>

        <!-- Recent images -->
        <div class="card">
          <div class="card-header">
            Ảnh Gần Đây
            <a href="admin.php?action=upload" class="btn btn-primary btn-sm">+ Tải Lên</a>
          </div>
          <div class="card-body">
            <?php $recent = array_slice(array_reverse($images), 0, 6); ?>
            <?php if (empty($recent)): ?>
              <p style="color:var(--muted)">Chưa có ảnh nào.</p>
            <?php else: ?>
              <div class="img-grid">
                <?php foreach ($recent as $img): ?>
                  <div>
                    <div class="img-thumb">
                      <img src="../images/<?= e($img) ?>" alt="<?= e($img) ?>" loading="lazy" />
                    </div>
                    <div class="img-name"><?= e($img) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Recent posts -->
        <div class="card">
          <div class="card-header">
            Bài Viết Gần Đây
            <a href="admin.php?action=new_post" class="btn btn-success btn-sm">+ Viết Bài</a>
          </div>
          <div class="card-body" style="padding:0">
            <?php if (empty($posts)): ?>
              <p style="padding:1.25rem;color:var(--muted)">Chưa có bài viết nào.</p>
            <?php else: ?>
              <table>
                <tr><th>Tiêu Đề</th><th>Danh Mục</th><th>Ngày</th><th>Thao Tác</th></tr>
                <?php foreach (array_slice(array_reverse($posts), 0, 5) as $p): ?>
                  <tr>
                    <td><?= e($p['title']) ?></td>
                    <td><?= e($p['category'] ?? '') ?></td>
                    <td><?= e($p['date'] ?? '') ?></td>
                    <td class="td-actions">
                      <a href="admin.php?action=edit_post&id=<?= e($p['id']) ?>" class="btn btn-primary btn-sm">Sửa</a>
                      <a href="admin.php?action=delete_post&id=<?= e($p['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Xóa bài này?')">Xóa</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </table>
            <?php endif; ?>
          </div>
        </div>

      <?php /* ========== UPLOAD ========== */ ?>
      <?php elseif ($action === 'upload'): ?>
        <div class="card">
          <div class="card-header">Tải Ảnh Lên</div>
          <div class="card-body">
            <form method="POST" action="admin.php?action=upload" enctype="multipart/form-data">
              <div class="form-group">
                <label>Chọn ảnh (JPG, PNG, GIF, WebP – tối đa 10 MB)</label>
                <input type="file" name="photo" accept="image/*" required />
              </div>
              <button type="submit" class="btn btn-primary">📤 Tải Lên</button>
            </form>
          </div>
        </div>

      <?php /* ========== IMAGES ========== */ ?>
      <?php elseif ($action === 'images'): ?>
        <div class="card">
          <div class="card-header">
            Tất Cả Hình Ảnh (<?= count($images) ?>)
            <a href="admin.php?action=upload" class="btn btn-primary btn-sm">+ Tải Lên</a>
          </div>
          <div class="card-body">
            <?php if (empty($images)): ?>
              <p style="color:var(--muted)">Chưa có ảnh nào.</p>
            <?php else: ?>
              <div class="img-grid">
                <?php foreach ($images as $img): ?>
                  <div>
                    <div class="img-thumb">
                      <img src="../images/<?= e($img) ?>" alt="<?= e($img) ?>" loading="lazy" />
                    </div>
                    <div class="img-name"><?= e($img) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

      <?php /* ========== POSTS LIST ========== */ ?>
      <?php elseif ($action === 'posts'): ?>
        <div class="card">
          <div class="card-header">
            Tất Cả Bài Viết (<?= count($posts) ?>)
            <a href="admin.php?action=new_post" class="btn btn-success btn-sm">+ Viết Bài</a>
          </div>
          <div class="card-body" style="padding:0">
            <?php if (empty($posts)): ?>
              <p style="padding:1.25rem;color:var(--muted)">Chưa có bài viết nào.</p>
            <?php else: ?>
              <table>
                <tr><th>#</th><th>Tiêu Đề</th><th>Danh Mục</th><th>Ngày</th><th>Thao Tác</th></tr>
                <?php foreach (array_reverse($posts) as $i => $p): ?>
                  <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($p['title']) ?></td>
                    <td><?= e($p['category'] ?? '—') ?></td>
                    <td><?= e($p['date'] ?? '—') ?></td>
                    <td class="td-actions">
                      <a href="admin.php?action=edit_post&id=<?= e($p['id']) ?>" class="btn btn-primary btn-sm">Sửa</a>
                      <a href="admin.php?action=delete_post&id=<?= e($p['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Xóa bài \"<?= e(addslashes($p['title'])) ?>\"?')">Xóa</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </table>
            <?php endif; ?>
          </div>
        </div>

      <?php /* ========== NEW / EDIT POST ========== */ ?>
      <?php elseif (in_array($action, ['new_post', 'edit_post'])): ?>
        <div class="card">
          <div class="card-header"><?= $editPost ? 'Chỉnh Sửa Bài Viết' : 'Viết Bài Mới' ?></div>
          <div class="card-body">
            <form method="POST" action="admin.php?action=save_post">
              <input type="hidden" name="id" value="<?= e($editPost['id'] ?? '') ?>" />
              <div class="form-group">
                <label>Tiêu đề *</label>
                <input type="text" name="title" value="<?= e($editPost['title'] ?? '') ?>" required />
              </div>
              <div class="form-group">
                <label>Danh mục</label>
                <select name="category">
                  <?php $cats = ['Kỹ Thuật','Thu Hoạch','Môi Trường','Sản Phẩm','Tin Tức'];
                  foreach ($cats as $c): ?>
                    <option <?= ($editPost['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Ảnh bìa (chọn từ ảnh đã tải lên)</label>
                <select name="image">
                  <option value="">-- Không chọn --</option>
                  <?php foreach ($images as $img): ?>
                    <option value="images/<?= e($img) ?>" <?= ($editPost['image'] ?? '') === 'images/' . $img ? 'selected' : '' ?>>
                      <?= e($img) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Nội dung *</label>
                <textarea name="body" rows="10" required><?= e($editPost['body'] ?? '') ?></textarea>
              </div>
              <div style="display:flex;gap:.75rem">
                <button type="submit" class="btn btn-success">💾 Lưu Bài</button>
                <a href="admin.php?action=posts" class="btn btn-sm" style="background:var(--border);color:var(--text)">Hủy</a>
              </div>
            </form>
          </div>
        </div>

      <?php endif; ?>

    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /layout -->

<?php endif; ?>
</body>
</html>
