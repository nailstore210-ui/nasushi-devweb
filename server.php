<?php
header("Content-Type: application/json; charset=utf-8");
require __DIR__ . '/vendor/autoload.php'; // Twilio
require __DIR__ . '/fpdf/fpdf.php'; // PDF

use Twilio\Rest\Client;

// الاتصال بقاعدة البيانات
error_reporting(E_ALL);
ini_set('display_errors', 1);

$connexion = new mysqli(
    getenv("DB_HOST"),
    getenv("DB_USER"),
    getenv("DB_PASS"),
    getenv("DB_NAME"),
    getenv("DB_PORT")
);

if ($connexion->connect_error) {
    die(json_encode([
        "status" => "error",
        "message" => "⚠️ Connection impossible: " . $connexion->connect_error
    ]));
}

echo json_encode([
    "status" => "success",
    "message" => "✅ Connected successfully!"
]);
function updatePrice($item, $choice) {
        $prices = [
        "California Roll" => [
            "THON" => 1090,
            "POULET CRISPY" => 1190,
            "VEGITARIEEN" => 1190,
            "CREVETTE" => 1390,
            "SAUMON" => 1390,
            "SURIMI" => 1390
        ],
        "Crispy Roll" => [
            "THON" => 1140,
            "POULET CRISPY" => 1240,
            "CREVETTE" => 1440,
            "SAUMON" => 1440,
            "SURIMI" => 1440
        ],
        "Futomaki" => [
            "THON" => 1090,
            "POULET CRISPY" => 1190,
            "CREVETTE" => 1390,
            "VEGITARIEEN" => 1190,
            "SAUMON" => 1390,
            "SURIMI" => 1390
        ],
        "Hosomaki" => [
            "THON" => 990,
            "POULET CRISPY" => 990,
            "CREVETTE" => 1190,
            "AVOCAT" => 1090,
            "SAUMON" => 1190,
            "SURIMI" => 1150
        ],
        "Dragon Roll" => [
            "THON" => 1690,
            "POULET CRISPY" => 1690,
            "CREVETTE" => 1890,
            "SAUMON" => 1890,
            "SURIMI" => 1890
        ],
        "Nigiri" => [
            "CREVETTE" => 1490,
            "SAUMON" => 1490,
            "AVOCAT" => 1290
        ],
        "Gyoza" => [
            "POULET" => 590,
            "VIANDE" => 790,
            "CREVETTE" => 990
        ],
        "Crunchy Roll" => [
            "THON" => 1140,
            "POULET CRISPY" => 1240,
            "CREVETTE" => 1440,
            "SAUMON" => 1440,
            "SURIMI" => 1440
        ],
        "Futomaki chesse" => [
            "THON" => 1190,
            "POULET CRISPY" => 1290,
            "VEGITARIEEN" => 1290,
            "CREVETTE" => 1490,
            "SAUMON" => 1490,
            "SURIMI" => 1490
        ],
        "California roll chesse" => [
            "THON" => 1190,
            "POULET CRISPY" => 1290,
            "VEGITARIEEN" => 1290,
            "CREVETTE" => 1490,
            "SAUMON" => 1490,
            "SURIMI" => 1490
        ],
        "Les Nems" => [
            "POULET" => 690,
            "VIANDE" => 890
        ]
    ];

    return $prices[$item][$choice] ?? 0;
}
function getDeliveryPrice($area) {
    $free = ["تفاحي","adll فلفلة","الفتوي","قرية لعرايس"];
    $hundred = ["بلاطان","القرية","الغطسة","ليابيي"];
    $oneFifty = ["شاطئ 8","شاطئ 10","الماناج"];
    $twoHundred = ["شاطئ 7","بوزعرورة","adll بوزعرورة","القرية السياحية","مارينا دور","سانتيفي","الجامعة","الاقامات الجامعية للإناث","الاقامات الجامعية للذكور"];
    $twoFifty = ["كوسيدار","جان دارك","لابيسين"];
    $threeHundred = ["33","حمادي كرومة","لحصاين","فالي","لاسيا","ليزالي","لبلاد","كامي","مرج الديب","بوبعلى","فوبور","واد الوحش","مسيون 1","مسيون 2","سانسو","سيسال","فاووث","ليباتيمو الشناوة","صالح بولكروة","زفزاف 1","زفزاف 2","الحدائق"];

    if(in_array($area, $free)) return 0;
    if(in_array($area, $hundred)) return 100;
    if(in_array($area, $oneFifty)) return 150;
    if(in_array($area, $twoHundred)) return 200;
    if(in_array($area, $twoFifty)) return 250;
    if(in_array($area, $threeHundred)) return 300;

    return -1;
}
// استقبال البيانات
$data = json_decode(file_get_contents("php://input"), true);
$name = $data['name'] ?? "";
$phone = $data['phone'] ?? "";
$area = $data['area'] ?? "";
$products = $data['products'] ?? [];
$usedPoints = intval($data['usedPoints'] ?? 0);
$time = date("Y-m-d H:i:s");

// تحقق من الهاتف والسلة
if(!preg_match("/^(\+213|0)[0-9]{9}$/", $phone)) {
    echo json_encode(["status"=>"error","message"=>"رقم الهاتف غير صالح"]);
    exit;
}

if(empty($products)) {
    echo json_encode(["status"=>"error","message"=>"السلة فارغة"]);
    exit;
}

// حساب الأسعار
$total = 0;
foreach($products as &$p){
    $p['price'] = updatePrice($p['name'],$p['choice']);
    $total += $p['price'];
}
$deliveryPrice = getDeliveryPrice($area);
if($deliveryPrice === -1){
    echo json_encode(["status"=>"error","message"=>"⚠️ المنطقة غير مدعومة"]);
    exit;
}
$total += $deliveryPrice;

// جلب أو إنشاء الزبون
$stmt = $connexion->prepare("SELECT * FROM customers WHERE phone=?");
$stmt->bind_param("s", $phone);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

if(!$customer){
    $stmt = $connexion->prepare("INSERT INTO customers (name, phone, points) VALUES (?,?,0)");
    $stmt->bind_param("ss", $name, $phone);
    $stmt->execute();
    $customerId = $stmt->insert_id;
    $points = 0;
} else {
    $customerId = $customer['id'];
    $points = $customer['points'];
}

// خصم وإضافة النقاط
if($points >= $usedPoints){
    $points -= $usedPoints;
} else {
    echo json_encode(["status"=>"error","message"=>"رصيد النقاط غير كافي"]);
    exit;
}
$earned = 0;
if($usedPoints == 0){
    $earned = floor($total/100);
    $points += $earned;
}
$stmt = $connexion->prepare("UPDATE customers SET points=? WHERE id=?");
$stmt->bind_param("ii", $points, $customerId);
$stmt->execute();

// تخزين الطلب
$stmt = $connexion->prepare("INSERT INTO orders (customer_id, area, total, points_used, points_earned) VALUES (?,?,?,?,?)");
$stmt->bind_param("isiii", $customerId, $area, $total, $usedPoints, $earned);
$stmt->execute();
$orderId = $stmt->insert_id;

// تخزين المنتجات
foreach($products as $p){
    $stmt = $connexion->prepare("INSERT INTO order_items (order_id, product_name, choice, price) VALUES (?,?,?,?)");
    $stmt->bind_param("isss", $orderId, $p['name'], $p['choice'], $p['price']);
    $stmt->execute();
}

// Twilio إشعار واتساب
$accountSid = getenv("TWILIO_ACCOUNT_SID");
$authToken = getenv("TWILIO_AUTH_TOKEN");
$twilio = new Client($accountSid, $authToken);
try {
    $twilio->messages->create(
        "whatsapp:+213792106084",
        [
            "from" => "whatsapp:+14155238886",
            "body" => "طلب جديد 🛒\nرقم الطلب: $orderId\nالاسم: $name\nالهاتف: $phone\nالمنطقة: $area\nالمجموع: $total DA\nالنقاط المستعملة: $usedPoints\nالنقاط المكتسبة: $earned\nالرصيد الجديد: $points\nالوقت: $time\nالمنتجات: ".implode(", ", array_map(fn($p)=>$p['name']."(".$p['choice'].")",$products))
        ]
    );
} catch(Exception $e){
    error_log("Twilio Error: ".$e->getMessage());
}

// توليد فاتورة PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont("Arial","B",16);
$pdf->Cell(0,10,"فاتورة الطلبية",0,1,"C");

$pdf->SetFont("Arial","",12);
$pdf->Cell(0,10,"رقم الطلب: ".$orderId,0,1);
$pdf->Cell(0,10,"الاسم: ".$name,0,1);
$pdf->Cell(0,10,"الهاتف: ".$phone,0,1);
$pdf->Cell(0,10,"المنطقة: ".$area,0,1);
$pdf->Cell(0,10,"الوقت: ".$time,0,1);

$pdf->Ln(10);
$pdf->SetFont("Arial","B",12);
$pdf->Cell(0,10,"المنتجات:",0,1);

$pdf->SetFont("Arial","",12);
foreach($products as $p){
    $pdf->Cell(0,10,$p['name']." (".$p['choice'].") - ".$p['price']." DA",0,1);
}

$pdf->Ln(10);
$pdf->Cell(0,10,"سعر التوصيل: ".$deliveryPrice." DA",0,1);
$pdf->Cell(0,10,"المجموع: ".$total." DA",0,1);
$pdf->Cell(0,10,"النقاط المستعملة: ".$usedPoints,0,1);
$pdf->Cell(0,10,"النقاط المكتسبة: ".$earned,0,1);
$pdf->Cell(0,10,"الرصيد الجديد: ".$points,0,1);

$pdf->Ln(20);
$pdf->SetFont("Arial","I",10);
$pdf->Cell(0,10,"📞 للتواصل: 0792 106 084",0,1,"C");
$pdf->Cell(0,10,"تابعنا على إنستغرام أو الفيس بوك: @Na_Sushi_21",0,1,"C");
$pdf->Cell(0,10,"🌐 موقعنا: https://nasushi-devweb.onrender.com/",0,1,"C");

$filePath = __DIR__."/invoice-$orderId.pdf";
$pdf->Output("F",$filePath);

// الرد JSON للواجهة
echo json_encode([
    "status"=>"success",
    "orderId"=>$orderId,
    "newBalance"=>$points,
    "pointsEarned"=>$earned,
    "total"=>$total,
    "deliveryPrice"=>$deliveryPrice,
    "invoice"=>"invoice-$orderId.pdf"
], JSON_UNESCAPED_UNICODE);
?>
