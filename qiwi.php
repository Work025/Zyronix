<?php
$login = $_POST['login'];
$pul = $_POST['sum'];
// echo "$pul $login";
header("location: https://qiwi.com/payment/form/99?extra%5B%27account%27%5D=+998997481822&amountInteger=$pul&amountFraction=00%C2%A4cy=643&blocked%5B0%5D=sum&blocked%5B1%5D=account&blocked%5B2%5D=comment&extra%5B%27comment%27%5D=$login");
?>