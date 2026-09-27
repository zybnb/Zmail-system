<?php
require __DIR__ . '/auth.php';
$db = get_db();

$msg = '';
$ok = false;

if (($_GET['action'] ?? '') === 'delete' && !empty($_GET['id'])) {
    $stmt = $db->prepare("SELECT label FROM accounts WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $label = $stmt->fetchColumn();
    if ($label) {
        $db->prepare("DELETE FROM accounts WHERE id = ?")->execute([(int)$_GET['id']]);
        $msg = "已删除账户「{$label}」。已收邮件仍保留在邮件库中。";
        $ok = true;
    }
}

if (($_GET['action'] ?? '') === 'toggle' && !empty($_GET['id'])) {
    $db->prepare("UPDATE accounts SET enabled = 1 - enabled WHERE id = ?")->execute([(int)$_GET['id']]);
    header('Location: accounts.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id       = (int)$_POST['id'];
    $label    = trim($_POST['label'] ?? '');
    $host     = trim($_POST['host'] ?? '');
    $user     = trim($_POST['user'] ?? '');
    $password = $_POST['password'] ?? '';
    $enabled  = isset($_POST['enabled']) ? 1 : 0;

    if ($label === '' || $host === '' || $user === '') {
        $msg = '显示名称、IMAP 地址、账号不能为空';
    } else {
        if ($password !== '') {
            $db->prepare("UPDATE accounts SET label=?, host=?, user=?, password=?, enabled=? WHERE id=?")
               ->execute([$label, $host, $user, $password, $enabled, $id]);
        } else {
            $db->prepare("UPDATE accounts SET label=?, host=?, user=?, enabled=? WHERE id=?")
               ->execute([$label, $host, $user, $enabled, $id]);
        }
        $msg = '已保存修改';
        $ok = true;
    }
}

$testResult = null;
if (($_GET['action'] ?? '') === 'test' && !empty($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $a = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($a) {
        $mbox = @imap_open($a['host'], $a['user'], $a['password'], 0, 1);
        if ($mbox) {
            $testResult = ['id' => $a['id'], 'ok' => true, 'msg' => '连接成功'];
            imap_close($mbox);
        } else {
            $testResult = ['id' => $a['id'], 'ok' => false, 'msg' => imap_last_error()];
        }
    }
}

$editAcct = null;
if (($_GET['action'] ?? '') === 'edit' && !empty($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $editAcct = $stmt->fetch(PDO::FETCH_ASSOC);
}

$accounts = $db->query("SELECT * FROM accounts ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$counts = [];
foreach ($db->query("SELECT account, COUNT(*) as cnt FROM mails GROUP BY account") as $row) {
    $counts[$row['account']] = $row['cnt'];
}

$page_title = '邮箱账户管理';
$nav_active = 'accounts';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>邮箱账户</h1>
    <div class="actions">
        <button class="btn btn-ghost" onclick="runFetch()">立即收信</button>
        <a href="add_account.php" class="btn btn-primary">+ 添加邮箱</a>
    </div>
</div>

<?php if ($msg): ?>
    <div class="msg <?= $ok ? 'msg-ok' : 'msg-err' ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($editAcct): ?>
<div class="card">
    <h3>编辑账户：<?= htmlspecialchars($editAcct['label']) ?></h3>
    <form method="post">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?= $editAcct['id'] ?>">
        <div class="form-row">
            <div>
                <label>显示名称</label>
                <input type="text" name="label" value="<?= htmlspecialchars($editAcct['label']) ?>" required>
            </div>
            <div>
                <label>标识（不可修改）</label>
                <input type="text" value="<?= htmlspecialchars($editAcct['key_name']) ?>" disabled>
            </div>
        </div>
        <label>IMAP 服务器地址</label>
        <input type="text" name="host" value="<?= htmlspecialchars($editAcct['host']) ?>" required>
        <div class="form-row">
            <div>
                <label>邮箱账号</label>
                <input type="text" name="user" value="<?= htmlspecialchars($editAcct['user']) ?>" required>
            </div>
            <div>
                <label>授权码 / 应用密码</label>
                <input type="password" name="password" placeholder="留空则不修改">
            </div>
        </div>
        <div class="checkbox-row">
            <input type="checkbox" name="enabled" id="edit_enabled" value="1" <?= $editAcct['enabled'] ? 'checked' : '' ?>>
            <label for="edit_enabled">启用</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存修改</button>
            <a href="accounts.php" class="btn btn-ghost">取消</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h3>已配置账户（<?= count($accounts) ?>）</h3>
    <?php if (empty($accounts)): ?>
        <div class="empty">
            还没有添加邮箱账户<br><br>
            <a href="add_account.php" class="btn btn-primary">+ 添加第一个邮箱</a>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>名称</th><th>账号</th><th>邮件数</th>
                    <th>状态</th><th>最近拉取</th><th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($accounts as $a): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($a['label']) ?></strong><br>
                        <code><?= htmlspecialchars($a['key_name']) ?></code>
                    </td>
                    <td class="word-break text-small"><?= htmlspecialchars($a['user']) ?></td>
                    <td><?= $counts[$a['key_name']] ?? 0 ?></td>
                    <td>
                        <span class="badge <?= $a['enabled'] ? 'badge-on' : 'badge-off' ?>">
                            <?= $a['enabled'] ? '启用' : '停用' ?>
                        </span>
                    </td>
                    <td class="text-small text-muted"><?= htmlspecialchars($a['last_fetch'] ?? '从未') ?></td>
                    <td class="actions-cell">
                        <a class="btn-sm btn-test" href="?action=test&id=<?= $a['id'] ?>">测试</a>
                        <a class="btn-sm btn-edit" href="?action=edit&id=<?= $a['id'] ?>">编辑</a>
                        <a class="btn-sm btn-toggle" href="?action=toggle&id=<?= $a['id'] ?>">
                            <?= $a['enabled'] ? '停用' : '启用' ?>
                        </a>
                        <a class="btn-sm btn-del" href="?action=delete&id=<?= $a['id'] ?>"
                           onclick="return confirm('确定删除账户「<?= htmlspecialchars($a['label']) ?>」？')">删除</a>
                    </td>
                </tr>
                <?php if ($testResult && $testResult['id'] == $a['id']): ?>
                    <tr><td colspan="6" style="padding-top:0;">
                        <div class="msg <?= $testResult['ok'] ? 'msg-ok' : 'msg-err' ?>" style="margin:0;">
                            <?= $testResult['ok'] ? 'OK' : 'ERR' ?> <?= htmlspecialchars($testResult['msg']) ?>
                        </div>
                    </td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>