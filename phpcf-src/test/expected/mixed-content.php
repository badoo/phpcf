<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetPageProperty(
    "title",
    "TItile"
);
$APPLICATION->SetPageProperty("NOT_SHOW_NAV_CHAIN", "Y");
$APPLICATION->SetTitle("Index");
?>
<div class="row">
    <div class="col-xs-6 col-sm-4 index_block">
        <div class="title_index_block"><span>menu</span></div>
        <?php
$APPLICATION->IncludeFile(
    $APPLICATION->GetCurDir() . "/include/include.php",
    Array(),
    Array(
        "MODE"      => "html",
        "NAME"      => "Content",
    )
);
?>
    </div>
