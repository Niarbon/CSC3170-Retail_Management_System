<?php
// 数据库配置（你自己的密码，不动）
$host = 'localhost';
$user = 'root';
$pwd = 'csc3170';
$dbname = 'csc3170_store';

// 先定义变量，防止未定义警告
$error = '';
$cols = [];
$rows = [];
$sql = $_POST['sql'] ?? '';

// 连接数据库 + 错误捕获
$conn = mysqli_connect($host, $user, $pwd, $dbname);
if (!$conn) {
    $error = "数据库连接失败：" . mysqli_connect_error();
} else {
    mysqli_set_charset($conn, 'utf8mb4');

    // 执行SQL
    if ($sql) {
        $result = mysqli_query($conn, $sql);
        if (mysqli_errno($conn)) {
            $error = mysqli_error($conn);
        } else {
            if (is_object($result)) {
                $cols = [];
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
<title>CSC3170 Store 管理系统</title>
<link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
<style>
    body{padding:30px; background:#f8f9fa;}
    .card{margin-bottom:20px; border-radius:10px;}
    textarea{font-family:consolas; font-size:14px;}
</style>
</head>
<body>
<div class="container">
    <h2 class="mb-4">🏪 CSC3170 Store 管理系统（SQL网页版）</h2>

    <!-- SQL执行面板 -->
    <div class="card">
        <div class="card-header bg-primary text-white">SQL 执行器</div>
        <div class="card-body">
            <form method="post">
                <textarea name="sql" class="form-control mb-3" rows="6" placeholder="输入你的SQL"><?=htmlspecialchars($sql)?></textarea>
                <button class="btn btn-success" type="submit">执行</button>
            </form>
        </div>
    </div>

    <!-- 错误提示 -->
    <?php if ($error): ?>
        <div class="alert alert-danger"><?=$error?></div>
    <?php endif ?>

    <!-- 结果表格 -->
    <?php if ($cols): ?>
        <div class="card">
            <div class="card-header">查询结果</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <?php foreach ($cols as $c): ?>
                                    <th><?=$c?></th>
                                <?php endforeach ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <?php foreach ($r as $v): ?>
                                        <td><?=htmlspecialchars((string)$v)?></td>
                                    <?php endforeach ?>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php elseif ($sql && !$error): ?>
        <div class="alert alert-success">执行成功</div>
    <?php endif ?>

    <!-- 快捷功能 -->
    <div class="card">
        <div class="card-header">🚀 常用功能（一键查询）</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <h6>👨‍💼 员工模块</h6>
                    <button class="btn btn-sm btn-outline-primary w-100 mb-1" onclick="setSql('SELECT * FROM employee WHERE is_active=1')">在职员工</button>
                    <button class="btn btn-sm btn-outline-primary w-100 mb-1" onclick="setSql('SELECT * FROM employee WHERE is_active=0')">离职员工</button>
                    <button class="btn btn-sm btn-outline-primary w-100" onclick="setSql('SELECT job_position,COUNT(*) cnt,AVG(salary) avg FROM employee GROUP BY job_position')">薪资统计</button>
                </div>
                <div class="col-md-6">
                    <h6>📦 商品 & 库存</h6>
                    <button class="btn btn-sm btn-outline-primary w-100 mb-1" onclick="setSql('SELECT p.*,c.category_name,s.supplier_name,i.quantity FROM product p JOIN category c ON p.category_id=c.category_id JOIN supplier s ON p.supplier_id=s.supplier_id JOIN inventory i ON p.product_id=i.product_id')">商品库存列表</button>
                    <button class="btn btn-sm btn-outline-primary w-100" onclick="setSql('SELECT * FROM supplier')">供应商列表</button>
                </div>
                <div class="col-md-6">
                    <h6>🛒 销售</h6>
                    <button class="btn btn-sm btn-outline-primary w-100" onclick="setSql('SELECT * FROM sales_transaction ORDER BY transaction_time DESC LIMIT 20')">最近20单</button>
                </div>
                <div class="col-md-6">
                    <h6>📥 采购</h6>
                    <button class="btn btn-sm btn-outline-primary w-100" onclick="setSql('SELECT * FROM purchase_order ORDER BY receive_time DESC LIMIT 20')">最近采购单</button>
                    <button class="btn btn-sm btn-outline-primary w-100" onclick="setSql('SELECT s.supplier_name,COUNT(*) freq,SUM(poi.subtotal) total FROM supplier s LEFT JOIN purchase_order po ON s.supplier_id=po.supplier_id LEFT JOIN purchase_order_item poi ON po.purchase_order_id=poi.purchase_order_id GROUP BY s.supplier_id')">供应商供货统计</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function setSql(sql) {
    document.querySelector('[name=sql]').value = sql;
}
</script>
</body>
</html>