<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>ZlsPHP Success</title>
</head>
<body>
<style>
    .layout {
        margin: 300px auto auto;
        width: 426px;
    }
    h2 {
        text-align: center;
    }
    table {
        width: 100%;
        text-align: left;
    }
    td:not(:nth-last-child(1)) {
        font-weight: bold;
        width: 130px;
        display: block;
        padding: 5px;
    }
</style>
<div class="layout">
    <div class="dib-box">
        <table>
            <tr>
                <td colspan='2'><h2>Hello world</h2></td>
            </tr>
            <tr>
                <td>Time</td>
                <td><?= $time ?></td>
            </tr>
            <tr>
                <td>host</td>
                <td><?= $host ?></td>
            </tr>
            <tr>
                <td>global</td>
                <td>runtime: <?= $global['runtime'] ?>, memory: <?= $global['memory'] ?></td>
            </tr>
            <tr>
                <td>part</td>
                <td>runtime: <?= $part['runtime'] ?>, memory: <?= $part['memory'] ?></td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
