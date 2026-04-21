<?php
$host = 'localhost';
$user = 'root';
$pwd = '081012';
$dbname = 'csc3170_store';

$error = '';
$cols = [];
$rows = [];
$sql = $_POST['sql'] ?? '';

$conn = mysqli_connect($host, $user, $pwd, $dbname);
if (!$conn) {
    $error = '数据库连接失败：' . mysqli_connect_error();
} else {
    mysqli_set_charset($conn, 'utf8mb4');

    if ($sql) {
        $result = mysqli_query($conn, $sql);
        if (mysqli_errno($conn)) {
            $error = mysqli_error($conn);
        } else {
            if (is_object($result)) {
                while ($c = mysqli_fetch_field($result)) {
                    $cols[] = $c->name;
                }
                $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CSC3170 Store 管理系统</title>
<link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
<style>
    :root {
        --bg: #0f1117;
        --bg-soft: rgba(20, 24, 35, 0.82);
        --panel: rgba(18, 22, 32, 0.9);
        --panel-strong: rgba(12, 15, 24, 0.96);
        --border: rgba(214, 181, 113, 0.22);
        --border-strong: rgba(214, 181, 113, 0.42);
        --text: #f4efe6;
        --muted: #b8ad9a;
        --gold: #d6b571;
        --gold-deep: #9f7b2f;
        --green: #7bc39b;
        --red: #ea8f85;
        --shadow: 0 28px 60px rgba(0, 0, 0, 0.42);
        --radius-xl: 28px;
        --radius-lg: 20px;
        --radius-md: 14px;
        --mono: "Iosevka", "SFMono-Regular", "Consolas", "Menlo", monospace;
        --serif: "STSong", "Songti SC", "Noto Serif CJK SC", "Source Han Serif SC", serif;
        --sans: "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        min-height: 100vh;
        padding: 40px 20px 56px;
        color: var(--text);
        font-family: var(--sans);
        background:
            radial-gradient(circle at top left, rgba(214, 181, 113, 0.18), transparent 28%),
            radial-gradient(circle at 85% 12%, rgba(58, 90, 120, 0.22), transparent 22%),
            linear-gradient(145deg, #090b10 0%, #0f1117 38%, #141925 100%);
        position: relative;
        overflow-x: hidden;
    }

    body::before,
    body::after {
        content: "";
        position: fixed;
        inset: 0;
        pointer-events: none;
    }

    body::before {
        background:
            linear-gradient(rgba(255, 255, 255, 0.028) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.028) 1px, transparent 1px);
        background-size: 30px 30px;
        mask-image: linear-gradient(to bottom, rgba(0, 0, 0, 0.58), transparent 92%);
    }

    body::after {
        opacity: 0.06;
        background-image:
            radial-gradient(circle at 20% 20%, #fff 0 1px, transparent 1px),
            radial-gradient(circle at 80% 35%, #fff 0 1px, transparent 1px),
            radial-gradient(circle at 45% 80%, #fff 0 1px, transparent 1px);
        background-size: 180px 180px;
        mix-blend-mode: soft-light;
    }

    .page-shell {
        width: min(1280px, 100%);
        margin: 0 auto;
        position: relative;
        z-index: 1;
    }

    .hero-panel,
    .panel,
    .result-panel,
    .message-panel {
        border: 1px solid var(--border);
        background: linear-gradient(180deg, rgba(23, 28, 40, 0.9), rgba(12, 15, 22, 0.92));
        box-shadow: var(--shadow);
        backdrop-filter: blur(14px);
    }

    .hero-panel {
        border-radius: var(--radius-xl);
        padding: 34px;
        display: grid;
        grid-template-columns: minmax(0, 1.8fr) minmax(280px, 0.9fr);
        gap: 24px;
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }

    .hero-panel::before {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(120deg, rgba(214, 181, 113, 0.12), transparent 45%, rgba(214, 181, 113, 0.08));
        pointer-events: none;
    }

    .eyebrow,
    .panel-kicker,
    .meta-label,
    .helper-label {
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.24em;
        font-size: 0.72rem;
        color: var(--gold);
    }

    .hero-title {
        margin: 12px 0 14px;
        font-family: var(--serif);
        font-size: clamp(2.4rem, 4vw, 4.2rem);
        line-height: 0.96;
        letter-spacing: 0.02em;
    }

    .hero-text {
        margin: 0;
        max-width: 58ch;
        font-size: 1.02rem;
        line-height: 1.8;
        color: var(--muted);
    }

    .hero-meta {
        display: grid;
        gap: 14px;
        align-content: center;
    }

    .meta-card {
        border: 1px solid rgba(214, 181, 113, 0.18);
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.03);
        padding: 18px 20px;
    }

    .meta-value {
        margin-top: 8px;
        font-family: var(--mono);
        font-size: 1rem;
        color: #fff6de;
        word-break: break-word;
    }

    .layout-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr);
        gap: 24px;
        align-items: start;
        margin-bottom: 24px;
    }

    .panel,
    .result-panel,
    .message-panel {
        border-radius: var(--radius-xl);
        padding: 28px;
    }

    .panel-header,
    .result-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 20px;
    }

    .panel-header h2,
    .result-header h2 {
        margin: 8px 0 0;
        font-family: var(--serif);
        font-size: 1.6rem;
        letter-spacing: 0.02em;
    }

    .status-badge {
        flex-shrink: 0;
        border-radius: 999px;
        padding: 10px 14px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        font-size: 0.86rem;
        color: var(--text);
        background: rgba(255, 255, 255, 0.04);
    }

    .status-ready {
        border-color: rgba(123, 195, 155, 0.32);
        color: #d5f5e2;
        background: rgba(123, 195, 155, 0.12);
    }

    .status-error {
        border-color: rgba(234, 143, 133, 0.34);
        color: #ffd8d3;
        background: rgba(234, 143, 133, 0.12);
    }

    .editor-form {
        display: grid;
        gap: 16px;
    }

    textarea.form-control {
        min-height: 320px;
        resize: vertical;
        border-radius: 22px;
        border: 1px solid rgba(214, 181, 113, 0.18);
        background:
            linear-gradient(180deg, rgba(14, 18, 27, 0.98), rgba(10, 13, 20, 0.98)),
            linear-gradient(180deg, rgba(214, 181, 113, 0.08), transparent);
        color: #f7f3ec;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        padding: 22px 24px;
        font-family: var(--mono);
        font-size: 0.98rem;
        line-height: 1.8;
    }

    textarea.form-control:focus {
        background-color: rgba(10, 13, 20, 0.98);
        color: #fff;
        border-color: var(--border-strong);
        box-shadow: 0 0 0 4px rgba(214, 181, 113, 0.12);
    }

    textarea.form-control::placeholder {
        color: rgba(244, 239, 230, 0.34);
    }

    .action-row {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        align-items: center;
        justify-content: space-between;
    }

    .btn-run {
        border: 0;
        border-radius: 999px;
        padding: 13px 24px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #111;
        background: linear-gradient(135deg, #f0d9a0, #c99639 52%, #f0d9a0);
        box-shadow: 0 12px 28px rgba(201, 150, 57, 0.28);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }

    .btn-run:hover {
        transform: translateY(-1px);
        box-shadow: 0 16px 34px rgba(201, 150, 57, 0.36);
    }

    .helper-text {
        color: var(--muted);
        font-size: 0.92rem;
    }

    .shortcut-groups {
        display: grid;
        gap: 18px;
    }

    .shortcut-section {
        border-radius: 20px;
        border: 1px solid rgba(214, 181, 113, 0.14);
        background: rgba(255, 255, 255, 0.025);
        padding: 18px;
    }

    .shortcut-section h3 {
        margin: 10px 0 14px;
        font-family: var(--serif);
        font-size: 1.15rem;
    }

    .button-grid {
        display: grid;
        gap: 10px;
    }

    .query-btn {
        width: 100%;
        text-align: left;
        border-radius: 14px;
        border: 1px solid rgba(214, 181, 113, 0.14);
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.045), rgba(255, 255, 255, 0.02));
        color: var(--text);
        padding: 12px 14px;
        transition: transform 0.18s ease, border-color 0.18s ease, background 0.18s ease;
    }

    .query-btn:hover {
        transform: translateX(3px);
        border-color: rgba(214, 181, 113, 0.38);
        background: linear-gradient(180deg, rgba(214, 181, 113, 0.12), rgba(255, 255, 255, 0.03));
    }

    .message-panel {
        margin-bottom: 24px;
    }

    .message-panel.error {
        border-color: rgba(234, 143, 133, 0.3);
        background: linear-gradient(180deg, rgba(53, 22, 20, 0.88), rgba(27, 12, 12, 0.92));
    }

    .message-panel.success {
        border-color: rgba(123, 195, 155, 0.28);
        background: linear-gradient(180deg, rgba(18, 46, 34, 0.88), rgba(12, 24, 19, 0.92));
    }

    .message-title {
        margin: 8px 0 0;
        font-family: var(--serif);
        font-size: 1.4rem;
    }

    .message-body {
        margin: 12px 0 0;
        color: #f6eee1;
        line-height: 1.8;
        word-break: break-word;
    }

    .result-panel {
        overflow: hidden;
    }

    .result-meta {
        color: var(--muted);
        font-size: 0.92rem;
    }

    .table-shell {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 1px;
    }

    .table {
        margin: 0;
        color: #f4efe6;
        min-width: 100%;
        background-color: transparent;
    }

    .table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        border-bottom: 1px solid rgba(214, 181, 113, 0.3);
        background: #0a0c14;
        color: var(--gold);
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        font-size: 0.78rem;
        padding: 16px 18px;
        white-space: nowrap;
    }

    .table tbody tr {
        transition: background 0.16s ease;
        background-color: transparent;
    }

    .table tbody tr:nth-child(odd) {
        background-color: rgba(255, 255, 255, 0.03);
    }

    .table tbody tr:nth-child(even) {
        background-color: transparent;
    }

    .table tbody tr:hover {
        background: rgba(214, 181, 113, 0.1);
    }

    .table tbody td {
        padding: 15px 18px;
        border-color: rgba(214, 181, 113, 0.12);
        vertical-align: top;
        white-space: nowrap;
        color: #f4efe6;
        background-color: transparent;
    }

    @media (max-width: 1024px) {
        .hero-panel,
        .layout-grid {
            grid-template-columns: 1fr;
        }

        .hero-panel,
        .panel,
        .result-panel,
        .message-panel {
            padding: 24px;
        }

        textarea.form-control {
            min-height: 260px;
        }
    }

    @media (max-width: 640px) {
        body {
            padding: 20px 12px 32px;
        }

        .hero-title {
            font-size: 2.1rem;
        }

        .panel-header,
        .result-header,
        .action-row {
            flex-direction: column;
            align-items: stretch;
        }

        .btn-run {
            width: 100%;
        }

        .table tbody td,
        .table thead th {
            white-space: normal;
        }
    }
</style>
</head>
<body>
<div class="page-shell">
    <section class="hero-panel">
        <div>
            <p class="eyebrow">CSC3170 Store</p>
            <h1 class="hero-title">数据库控制台</h1>
            <p class="hero-text">这是一个面向课程项目的轻量 SQL 工作台。保留原来的执行方式和快捷查询，只把输入、反馈和结果浏览体验重新整理得更清晰。</p>
        </div>
        <div class="hero-meta">
            <div class="meta-card">
                <p class="meta-label">Database</p>
                <div class="meta-value"><?=htmlspecialchars($dbname, ENT_QUOTES, 'UTF-8')?></div>
            </div>
            <div class="meta-card">
                <p class="meta-label">Interface</p>
                <div class="meta-value">Web SQL Runner</div>
            </div>
            <div class="meta-card">
                <p class="meta-label">Mode</p>
                <div class="meta-value">Read / Write Query Execution</div>
            </div>
        </div>
    </section>

    <div class="layout-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-kicker">SQL Workspace</p>
                    <h2>输入并执行语句</h2>
                </div>
                <span class="status-badge <?=$error ? 'status-error' : 'status-ready'?>"><?=$error ? '执行异常' : '准备就绪'?></span>
            </div>

            <form method="post" class="editor-form">
                <textarea name="sql" class="form-control" rows="6" placeholder="输入你的 SQL 语句"><?=htmlspecialchars($sql, ENT_QUOTES, 'UTF-8')?></textarea>
                <div class="action-row">
                    <button class="btn-run" type="submit">执行 SQL</button>
                    <span class="helper-text"><?=$sql ? '当前文本框中已保留上一次执行的 SQL。' : '支持直接输入查询、更新或删除语句。'?></span>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-kicker">Quick Queries</p>
                    <h2>常用查询入口</h2>
                </div>
            </div>

            <div class="shortcut-groups">
                <section class="shortcut-section">
                    <p class="helper-label">Employee</p>
                    <h3>员工模块</h3>
                    <div class="button-grid">
                        <button class="query-btn" type="button" onclick="setSql('SELECT * FROM employee WHERE is_active=1')">在职员工</button>
                        <button class="query-btn" type="button" onclick="setSql('SELECT * FROM employee WHERE is_active=0')">离职员工</button>
                        <button class="query-btn" type="button" onclick="setSql('SELECT job_position,COUNT(*) cnt,AVG(salary) avg FROM employee GROUP BY job_position')">薪资统计</button>
                    </div>
                </section>

                <section class="shortcut-section">
                    <p class="helper-label">Inventory</p>
                    <h3>商品与库存</h3>
                    <div class="button-grid">
                        <button class="query-btn" type="button" onclick="setSql('SELECT p.*,c.category_name,s.supplier_name,i.quantity FROM product p JOIN category c ON p.category_id=c.category_id JOIN supplier s ON p.supplier_id=s.supplier_id JOIN inventory i ON p.product_id=i.product_id')">商品库存列表</button>
                        <button class="query-btn" type="button" onclick="setSql('SELECT * FROM supplier')">供应商列表</button>
                    </div>
                </section>

                <section class="shortcut-section">
                    <p class="helper-label">Sales</p>
                    <h3>销售模块</h3>
                    <div class="button-grid">
                        <button class="query-btn" type="button" onclick="setSql('SELECT * FROM sales_transaction ORDER BY transaction_time DESC LIMIT 20')">最近 20 单</button>
                    </div>
                </section>

                <section class="shortcut-section">
                    <p class="helper-label">Purchase</p>
                    <h3>采购模块</h3>
                    <div class="button-grid">
                        <button class="query-btn" type="button" onclick="setSql('SELECT * FROM purchase_order ORDER BY receive_time DESC LIMIT 20')">最近采购单</button>
                    </div>
                </section>
            </div>
        </section>
    </div>

    <?php if ($error): ?>
        <section class="message-panel error">
            <p class="panel-kicker">Execution Feedback</p>
            <h2 class="message-title">执行失败</h2>
            <p class="message-body"><?=htmlspecialchars($error, ENT_QUOTES, 'UTF-8')?></p>
        </section>
    <?php endif ?>

    <?php if ($cols): ?>
        <section class="result-panel">
            <div class="result-header">
                <div>
                    <p class="panel-kicker">Result Grid</p>
                    <h2>查询结果</h2>
                </div>
                <div class="result-meta"><?=count($rows)?> 行，<?=count($cols)?> 列</div>
            </div>
            <div class="table-shell">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <?php foreach ($cols as $c): ?>
                                <th><?=htmlspecialchars($c, ENT_QUOTES, 'UTF-8')?></th>
                            <?php endforeach ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <?php foreach ($r as $v): ?>
                                    <td><?=htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8')?></td>
                                <?php endforeach ?>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php elseif ($sql && !$error): ?>
        <section class="message-panel success">
            <p class="panel-kicker">Execution Feedback</p>
            <h2 class="message-title">执行成功</h2>
            <p class="message-body">语句已成功执行，当前没有可展示的结果表。</p>
        </section>
    <?php endif ?>
</div>

<script>
function setSql(sql) {
    const textarea = document.querySelector('[name=sql]');
    textarea.value = sql;
    textarea.focus();
    textarea.setSelectionRange(textarea.value.length, textarea.value.length);
}
</script>
</body>
</html>
