<?php
/**
 * @global CMain $APPLICATION
 */

use Bitrix\Sale\OrderTable,
    Bitrix\Main\Loader,
    Bitrix\Main\UI\PageNavigation,
    Bitrix\Main\Entity\ReferenceField,
    Bitrix\Main\Grid\Options as GridOptions;

function d($value){
    echo "<pre>";
    print_r($value);
    echo "</pre>";
}

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
Loader::includeModule('sale');

$arResult = [
    "GRID_ID" => OrderTable::getTableName(),
    "ORDERS" => [],
    "COLUMNS" => [
        [
            "id" => 'ID',
            "name" => 'ID',
            "sort" => 'ID',
            "default" => true
        ],
        [
            "id" => 'USER',
            "name" => "Прізвище і ім'я користувача",
            "default" => true
        ],
        [
            "id" => 'STATUS_NAME',
            "name" => "Статус замовлення",
            "sort" => 'STATUS_NAME',
            "default" => true
        ],
        [
            "id" => 'DATE_INSERT',
            "name" => "Дата створення",
            "sort" => 'DATE_INSERT',
            "default" => true
        ]
    ],
    "ROWS" => [],
    "OPTIONS" => null,
    "ORDER" => [],
    "NAV_PARAMS" => [],
    "NAV" => [],
    "NAV_COUNT" => [],
    "STATUSES" => []
];

$filterOption = new Bitrix\Main\UI\Filter\Options($arResult["GRID_ID"]);
$filterFields = $filterOption->getFilter();

$arResult["OPTIONS"] = new GridOptions($arResult["GRID_ID"]);
$arResult["ORDER"] = $arResult["OPTIONS"]->GetSorting(['sort' => ['ID' => 'DESC'], 'vars' => ['by' => 'by', 'order' => 'order']]);

$arResult["NAV_PARAMS"] = $arResult["OPTIONS"]->GetNavParams();
$arResult["NAV"] = new PageNavigation($arResult["GRID_ID"]);

$arResult["NAV"]->allowAllRecords(true)
    ->setPageSize($arResult["NAV_PARAMS"]['nPageSize'])
    ->initFromUri();


$arResult["NAV"]->setPageSize($arResult["NAV_PARAMS"]['nPageSize']);

$filter = [];
foreach ($filterFields as $key => $value)
{
    switch ($key){
        // integer
        case "ID_from":
            $filter[">=ID"] = $value;
            break;
        case "ID_to":
            $filter["<=ID"] = $value;
            break;
        case "DATE_INSERT_from":
            $filter[">=DATE_INSERT"] = $value;
            break;
        case "DATE_INSERT_to":
            $filter["<=DATE_INSERT"] = $value;
            break;
        case "STATUS_NAME":
            $filter["STATUS_ID"] = $value;
            break;
        case "USER":
            $filter = [
                'LOGIC'=>'OR',
                ["%USER.NAME" => $value],
                ["%USER.LAST_NAME" => $value]
            ];
            break;

    }
}

$ui_filter = [];

$values = Bitrix\Sale\Internals\StatusLangTable::getList(["filter" => ["LID" => LANGUAGE_ID]]);
while ($value = $values->fetch())
    $arResult["STATUSES"][$value["STATUS_ID"]] = $value["NAME"];

foreach ($arResult["COLUMNS"] as $value) {
    switch ($value["id"]) {
        case "ID":
            $ui_filter[] = [
                "id" => $value["id"],
                "name" => $value["name"],
                "type" => "number",
                "default" => false
            ];
            break;
        case "STATUS_NAME":
            $ui_filter[] = [
                "id" => $value["id"],
                "name" => $value["name"],
                "default" => true,
                "type" => "list",
                "items" => $arResult["STATUSES"],
            ];
            break;
        case "DATE_INSERT":
            $ui_filter[] = [
                "id" => $value["id"],
                "name" => $value["name"],
                "type" => "date",
                "default" => true
            ];
            break;
        default:
            $ui_filter[] = [
                "id" => $value["id"],
                "name" => $value["name"],
                "type" => "text",
                "default" => true
            ];

    }
}


ob_start();
$APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', [
    'FILTER_ID' => $arResult["GRID_ID"],
    'GRID_ID' => $arResult["GRID_ID"],
    'FILTER' => $ui_filter,
    'ENABLE_LIVE_SEARCH' => true,
    'ENABLE_LABEL' => true
]);
$filterLayout = ob_get_clean();

$values = OrderTable::getList(['filter' => $filter, 'select' => ["ID"]]);
$arResult["NAV_COUNT"] = $values->getSelectedRowsCount();
$arResult["NAV"]->setRecordCount($arResult["NAV_COUNT"]);

$values = OrderTable::getList([
    'filter' => $filter,
    'offset' => $arResult["NAV"]->getOffset(),
    'limit'  => $arResult["NAV"]->getLimit(),
    'order'  => $arResult["ORDER"]["sort"],
    'select' => [
        'ID',
        'STATUS_ID',
        'STATUS_NAME' => 'STATUS.NAME',
        'DATE_INSERT',
        'USER_ID',
        'USER_NAME' => 'USER.NAME',
        'USER_LAST_NAME' => 'USER.LAST_NAME'
    ],
    'runtime' => [
        new ReferenceField(
            "USER",
            "Bitrix\Main\UserTable",
            ["ref.ID" => "this.USER_ID"],
            ["join_type" => 'inner']
        ),
        new ReferenceField(
            'STATUS',
            'Bitrix\Sale\Internals\StatusLangTable',
            ['=this.STATUS_ID' => 'ref.STATUS_ID'],
            ["join_type" => 'inner']
        )

    ]
]);
// OrderTable

$data = [];

while ($value = $values->fetch())
{
    foreach ($arResult["COLUMNS"] as $column){
        switch ($column["id"]){
            case "ID":
                $data[$column["id"]] = '<strong>' . $value[$column["id"]] . "</strong>";
                break;
            case "USER":
                $data[$column["id"]] = implode(" ", [$value['USER_LAST_NAME'], $value['USER_NAME']]);
                break;
            case "STATUS":
                $data[$column["id"]] = $value['STATUS_NAME'];
                break;
            default:
                $data[$column["id"]] = $value[$column["id"]];
        }

    }

    $arResult["ROWS"][] = [
        'data' => $data,
        'actions' => []
    ];
}
unset($data, $value, $values);

$arParams = [
    "GRID" => [
        'GRID_ID' => $arResult["GRID_ID"],
        'COLUMNS' => $arResult["COLUMNS"],
        'ROWS' => $arResult["ROWS"],
        'AJAX_MODE'           => 'Y',
        'AJAX_OPTION_JUMP'    => 'N',
        'AJAX_OPTION_HISTORY' => 'N',
        'AJAX_ID' => \CAjax::getComponentID('bitrix:main.ui.grid', '.default', ''),
        'SHOW_ROW_CHECKBOXES'       => false,
        'SHOW_CHECK_ALL_CHECKBOXES' => false,
        'SHOW_ROW_ACTIONS_MENU'     => false,
        'SHOW_GRID_SETTINGS_MENU'   => false,
        'SHOW_SELECTED_COUNTER'     => true,
        'SHOW_TOTAL_COUNTER'        => true,
        'SHOW_PAGESIZE'             => true,
        'PAGE_SIZES' => [
            ['NAME' => "5", 'VALUE' => '5'],
            ['NAME' => '10', 'VALUE' => '10'],
            ['NAME' => '20', 'VALUE' => '20'],
            ['NAME' => '50', 'VALUE' => '50']
        ],
        'ALLOW_COLUMNS_SORT'        => true,
        'ALLOW_COLUMNS_RESIZE'      => true,
        'ALLOW_HORIZONTAL_SCROLL'   => true,
        'ALLOW_SORT'                => true,
        'ALLOW_PIN_HEADER'          => true,
        'SHOW_ACTION_PANEL'         => false,
        'SHOW_NAVIGATION_PANEL'     => true,
        'SHOW_PAGINATION'           => true,
        'NAV_OBJECT' => $arResult["NAV"],
        "TOTAL_ROWS_COUNT" => $arResult["NAV_COUNT"],
    ],
    "PANEL" => array('LIST' => [
        [
            'type' => 'filter',
            'content' => $filterLayout
        ]
    ])
];

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$APPLICATION->IncludeComponent("bitrix:sender.ui.panel.title", "", $arParams["PANEL"]);
$APPLICATION->IncludeComponent("bitrix:main.ui.grid", "", $arParams["GRID"]);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
?>