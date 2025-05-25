<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "student_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to generate email from name
function generateEmail($name) {
    $name = strtolower($name);
    $name = str_replace(['.', ','], '', $name);
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) {
        return $parts[0] . '.' . $parts[1] . '@student.college.edu';
    } else {
        return str_replace(' ', '', $name) . '@student.college.edu';
    }
}

// Function to generate password
function generatePassword($rollNumber) {
    return 'Student@' . substr($rollNumber, -3);
}

// Function to determine section based on roll number
function getSection($rollNumber) {
    // CS-A Section: 22N81A6201-22N81A6267 (INCLUDING 22N81A6208)
    // CS-B Section: 22N81A6268-22N81A62C8

    // Define CS-A roll numbers (22N81A6201 to 22N81A6267, INCLUDING 22N81A6208)
    $cs_a_rolls = [];
    for ($i = 201; $i <= 267; $i++) {
        $cs_a_rolls[] = '22N81A6' . sprintf('%03d', $i);
    }

    // Check if current roll number is in CS-A list
    if (in_array($rollNumber, $cs_a_rolls)) {
        return 'CS-A';
    } else {
        // All other roll numbers (22N81A6268 onwards) are CS-B
        return 'CS-B';
    }
}

echo "<h2>Adding New Students Data</h2>";

// First, add all roll numbers to roll_numbers table
echo "<h3>Adding Roll Numbers...</h3>";

$roll_numbers_data = [
    ['22N81A6201', 2022, 'N81', 'A62', 'Cyber Security', '01'],
    ['22N81A6202', 2022, 'N81', 'A62', 'Cyber Security', '02'],
    ['22N81A6203', 2022, 'N81', 'A62', 'Cyber Security', '03'],
    ['22N81A6204', 2022, 'N81', 'A62', 'Cyber Security', '04'],
    ['22N81A6205', 2022, 'N81', 'A62', 'Cyber Security', '05'],
    ['22N81A6206', 2022, 'N81', 'A62', 'Cyber Security', '06'],
    ['22N81A6207', 2022, 'N81', 'A62', 'Cyber Security', '07'],
    ['22N81A6208', 2022, 'N81', 'A62', 'Cyber Security', '08'],
    ['22N81A6211', 2022, 'N81', 'A62', 'Cyber Security', '11'],
    ['22N81A6212', 2022, 'N81', 'A62', 'Cyber Security', '12'],
    ['22N81A6213', 2022, 'N81', 'A62', 'Cyber Security', '13'],
    ['22N81A6214', 2022, 'N81', 'A62', 'Cyber Security', '14'],
    ['22N81A6215', 2022, 'N81', 'A62', 'Cyber Security', '15'],
    ['22N81A6217', 2022, 'N81', 'A62', 'Cyber Security', '17'],
    ['22N81A6218', 2022, 'N81', 'A62', 'Cyber Security', '18'],
    ['22N81A6219', 2022, 'N81', 'A62', 'Cyber Security', '19'],
    ['22N81A6220', 2022, 'N81', 'A62', 'Cyber Security', '20'],
    ['22N81A6221', 2022, 'N81', 'A62', 'Cyber Security', '21'],
    ['22N81A6222', 2022, 'N81', 'A62', 'Cyber Security', '22'],
    ['22N81A6223', 2022, 'N81', 'A62', 'Cyber Security', '23'],
    ['22N81A6224', 2022, 'N81', 'A62', 'Cyber Security', '24'],
    ['22N81A6225', 2022, 'N81', 'A62', 'Cyber Security', '25'],
    ['22N81A6226', 2022, 'N81', 'A62', 'Cyber Security', '26'],
    ['22N81A6227', 2022, 'N81', 'A62', 'Cyber Security', '27'],
    ['22N81A6228', 2022, 'N81', 'A62', 'Cyber Security', '28'],
    ['22N81A6229', 2022, 'N81', 'A62', 'Cyber Security', '29'],
    ['22N81A6230', 2022, 'N81', 'A62', 'Cyber Security', '30'],
    ['22N81A6232', 2022, 'N81', 'A62', 'Cyber Security', '32'],
    ['22N81A6233', 2022, 'N81', 'A62', 'Cyber Security', '33'],
    ['22N81A6234', 2022, 'N81', 'A62', 'Cyber Security', '34'],
    ['22N81A6235', 2022, 'N81', 'A62', 'Cyber Security', '35'],
    ['22N81A6236', 2022, 'N81', 'A62', 'Cyber Security', '36'],
    ['22N81A6237', 2022, 'N81', 'A62', 'Cyber Security', '37'],
    ['22N81A6239', 2022, 'N81', 'A62', 'Cyber Security', '39'],
    ['22N81A6240', 2022, 'N81', 'A62', 'Cyber Security', '40'],
    ['22N81A6241', 2022, 'N81', 'A62', 'Cyber Security', '41'],
    ['22N81A6242', 2022, 'N81', 'A62', 'Cyber Security', '42'],
    ['22N81A6243', 2022, 'N81', 'A62', 'Cyber Security', '43'],
    ['22N81A6244', 2022, 'N81', 'A62', 'Cyber Security', '44'],
    ['22N81A6245', 2022, 'N81', 'A62', 'Cyber Security', '45'],
    ['22N81A6246', 2022, 'N81', 'A62', 'Cyber Security', '46'],
    ['22N81A6247', 2022, 'N81', 'A62', 'Cyber Security', '47'],
    ['22N81A6248', 2022, 'N81', 'A62', 'Cyber Security', '48'],
    ['22N81A6249', 2022, 'N81', 'A62', 'Cyber Security', '49'],
    ['22N81A6250', 2022, 'N81', 'A62', 'Cyber Security', '50'],
    ['22N81A6251', 2022, 'N81', 'A62', 'Cyber Security', '51'],
    ['22N81A6252', 2022, 'N81', 'A62', 'Cyber Security', '52'],
    ['22N81A6253', 2022, 'N81', 'A62', 'Cyber Security', '53'],
    ['22N81A6254', 2022, 'N81', 'A62', 'Cyber Security', '54'],
    ['22N81A6255', 2022, 'N81', 'A62', 'Cyber Security', '55'],
    ['22N81A6256', 2022, 'N81', 'A62', 'Cyber Security', '56'],
    ['22N81A6257', 2022, 'N81', 'A62', 'Cyber Security', '57'],
    ['22N81A6258', 2022, 'N81', 'A62', 'Cyber Security', '58'],
    ['22N81A6260', 2022, 'N81', 'A62', 'Cyber Security', '60'],
    ['22N81A6261', 2022, 'N81', 'A62', 'Cyber Security', '61'],
    ['22N81A6262', 2022, 'N81', 'A62', 'Cyber Security', '62'],
    ['22N81A6263', 2022, 'N81', 'A62', 'Cyber Security', '63'],
    ['22N81A6264', 2022, 'N81', 'A62', 'Cyber Security', '64'],
    ['22N81A6265', 2022, 'N81', 'A62', 'Cyber Security', '65'],
    ['22N81A6266', 2022, 'N81', 'A62', 'Cyber Security', '66'],
    ['22N81A6267', 2022, 'N81', 'A62', 'Cyber Security', '67'],
    ['22N81A6269', 2022, 'N81', 'A62', 'Cyber Security', '69'],
    ['22N81A6270', 2022, 'N81', 'A62', 'Cyber Security', '70'],
    ['22N81A6271', 2022, 'N81', 'A62', 'Cyber Security', '71'],
    ['22N81A6272', 2022, 'N81', 'A62', 'Cyber Security', '72'],
    ['22N81A6273', 2022, 'N81', 'A62', 'Cyber Security', '73'],
    ['22N81A6274', 2022, 'N81', 'A62', 'Cyber Security', '74'],
    ['22N81A6275', 2022, 'N81', 'A62', 'Cyber Security', '75'],
    ['22N81A6276', 2022, 'N81', 'A62', 'Cyber Security', '76'],
    ['22N81A6277', 2022, 'N81', 'A62', 'Cyber Security', '77'],
    ['22N81A6278', 2022, 'N81', 'A62', 'Cyber Security', '78'],
    ['22N81A6279', 2022, 'N81', 'A62', 'Cyber Security', '79'],
    ['22N81A6280', 2022, 'N81', 'A62', 'Cyber Security', '80'],
    ['22N81A6281', 2022, 'N81', 'A62', 'Cyber Security', '81'],
    ['22N81A6282', 2022, 'N81', 'A62', 'Cyber Security', '82'],
    ['22N81A6283', 2022, 'N81', 'A62', 'Cyber Security', '83'],
    ['22N81A6284', 2022, 'N81', 'A62', 'Cyber Security', '84'],
    ['22N81A6285', 2022, 'N81', 'A62', 'Cyber Security', '85'],
    ['22N81A6286', 2022, 'N81', 'A62', 'Cyber Security', '86'],
    ['22N81A6287', 2022, 'N81', 'A62', 'Cyber Security', '87'],
    ['22N81A6288', 2022, 'N81', 'A62', 'Cyber Security', '88'],
    ['22N81A6289', 2022, 'N81', 'A62', 'Cyber Security', '89'],
    ['22N81A6290', 2022, 'N81', 'A62', 'Cyber Security', '90'],
    ['22N81A6291', 2022, 'N81', 'A62', 'Cyber Security', '91'],
    ['22N81A6292', 2022, 'N81', 'A62', 'Cyber Security', '92'],
    ['22N81A6293', 2022, 'N81', 'A62', 'Cyber Security', '93'],
    ['22N81A6294', 2022, 'N81', 'A62', 'Cyber Security', '94'],
    ['22N81A6295', 2022, 'N81', 'A62', 'Cyber Security', '95'],
    ['22N81A6296', 2022, 'N81', 'A62', 'Cyber Security', '96'],
    ['22N81A6297', 2022, 'N81', 'A62', 'Cyber Security', '97'],
    ['22N81A6298', 2022, 'N81', 'A62', 'Cyber Security', '98'],
    ['22N81A6299', 2022, 'N81', 'A62', 'Cyber Security', '99'],
    ['22N81A62A0', 2022, 'N81', 'A62', 'Cyber Security', 'A0'],
    ['22N81A62A1', 2022, 'N81', 'A62', 'Cyber Security', 'A1'],
    ['22N81A62A2', 2022, 'N81', 'A62', 'Cyber Security', 'A2'],
    ['22N81A62A3', 2022, 'N81', 'A62', 'Cyber Security', 'A3'],
    ['22N81A62A4', 2022, 'N81', 'A62', 'Cyber Security', 'A4'],
    ['22N81A62A5', 2022, 'N81', 'A62', 'Cyber Security', 'A5'],
    ['22N81A62A6', 2022, 'N81', 'A62', 'Cyber Security', 'A6'],
    ['22N81A62A7', 2022, 'N81', 'A62', 'Cyber Security', 'A7'],
    ['22N81A62A8', 2022, 'N81', 'A62', 'Cyber Security', 'A8'],
    ['22N81A62A9', 2022, 'N81', 'A62', 'Cyber Security', 'A9'],
    ['22N81A62B0', 2022, 'N81', 'A62', 'Cyber Security', 'B0'],
    ['22N81A62B4', 2022, 'N81', 'A62', 'Cyber Security', 'B4'],
    ['22N81A62B5', 2022, 'N81', 'A62', 'Cyber Security', 'B5'],
    ['22N81A62B6', 2022, 'N81', 'A62', 'Cyber Security', 'B6'],
    ['22N81A62B7', 2022, 'N81', 'A62', 'Cyber Security', 'B7'],
    ['22N81A62B8', 2022, 'N81', 'A62', 'Cyber Security', 'B8'],
    ['22N81A62B9', 2022, 'N81', 'A62', 'Cyber Security', 'B9'],
    ['22N81A62C0', 2022, 'N81', 'A62', 'Cyber Security', 'C0'],
    ['22N81A62C1', 2022, 'N81', 'A62', 'Cyber Security', 'C1'],
    ['22N81A62C2', 2022, 'N81', 'A62', 'Cyber Security', 'C2'],
    ['22N81A62C5', 2022, 'N81', 'A62', 'Cyber Security', 'C5'],
    ['22N81A62C6', 2022, 'N81', 'A62', 'Cyber Security', 'C6'],
    ['22N81A62C7', 2022, 'N81', 'A62', 'Cyber Security', 'C7'],
    ['22N81A62C8', 2022, 'N81', 'A62', 'Cyber Security', 'C8']
];

foreach ($roll_numbers_data as $roll_data) {
    $check = $conn->query("SELECT id FROM roll_numbers WHERE roll_number = '{$roll_data[0]}'");
    if ($check->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO roll_numbers (roll_number, year_of_joining, college_code, dept_code, dept_name, student_number) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sissss", $roll_data[0], $roll_data[1], $roll_data[2], $roll_data[3], $roll_data[4], $roll_data[5]);
        if ($stmt->execute()) {
            echo "<li>Added roll number: {$roll_data[0]}</li>";
        }
        $stmt->close();
    }
}

echo "<h3>Adding Student Data...</h3>";

// Student data array
$students_data = [
    ['22N81A6201', 'SARA SYED NOORUDDIN', 'SYED NOORUDDIN', '2004-06-22', 'O+', '891827000000', '9700155612', 'Janapriya Mahanagar, Meerpet, Hyderabad'],
    ['22N81A6202', 'MANDA SAMYUKTHA', 'MANDA MAHENDER', '2004-12-11', 'O+', '534192000000', '8885088288', 'Rajbhavan Staff Quarters, Somajiguda, Hyderabad'],
    ['22N81A6203', 'V. SHIVA SRAVANI', 'V. ANJANEYULU', '2003-12-12', 'O+', '650684000000', '8688909906', 'Chinthal Cherukupally Colony'],
    ['22N81A6204', 'NAGOJU SRILAXMI', 'NAGOJU BRAHMA CHARY', '2005-02-08', 'B+', '532572000000', '9542029245', 'Vinayaka Hills Ph-2, Almasguda, B.N Reddy, R.R District'],
    ['22N81A6205', 'DUNDHIGALLA SATYA PRIYA', 'D. SANTOSH KUMAR', '2003-09-28', 'O+', '659866000000', '9959883464', 'Prashanthi Hills, Meerpet, Rangareddy, Telangana'],
    ['22N81A6206', 'KOMPALLY POORVIKA', 'KOMPALLY SRINIVAS REDDY', '2005-05-19', 'O+', '279617000000', '9908086733', 'Abhyudaya Apartments, HUDA Complex, Saroornagar'],
    ['22N81A6207', 'GURRAMPATI MANOHAR REDDY', 'GURRAMPATI MALYADRI REDDY', '2004-11-04', 'O+', '277375000000', '9550962846', 'Venkateshwara Colony, Hastinapuram'],
    ['22N81A6208', 'GIRKATI NAVAMANIDEEP GOUD', 'GIRKATI KRISHNAM RAJU', '2005-04-27', 'O+', '825143000000', '7671944125', '2-65 Balapur'],
    ['22N81A6211', 'MD AMER KHAN', 'MD AZAM KHAN', '2003-11-15', 'O+', '826187000000', '8328263797', 'Ameenpur, Sangareddy'],
    ['22N81A6212', 'VOJA RAVI CHANDU', 'VOJA RAMA KRISHNA', '2004-07-28', 'B-', '581948000000', '9848952046', 'Khanapur, Nizamabad District'],
    ['22N81A6213', 'SAKINALA MANOJ', 'SAKINALA SRINIVASU', '2005-03-22', 'B+', '780195000000', '9959528829', 'Harihara Puram, BN Reddy Nagar'],
    ['22N81A6214', 'ANNAKUSA RAHUL', 'ANNAKUSA ILAIAH', '2004-01-21', '', '217837000000', '9618993026', 'Rock Town Colony'],
    ['22N81A6215', 'UTKARSH PAL', 'DEEPCHAND PAL', '2004-07-24', 'B+', '650573000000', '9573459810', 'Laxmiguda, RGK Colony, Phase-2'],
    ['22N81A6217', 'ADLA SIDDU', 'ADLA VENKAT REDDY', '2003-09-22', 'O+', '223692000000', '8978072808', 'BN Reddy'],
    ['22N81A6218', 'GOVINDU MANISH KUMAR REDDY', 'GOVINDU BHASKAR REDDY', '2004-08-17', 'O+', '264101000000', '8179610761', 'TNGOS Colony, Kattedan, Rajendra Nagar'],
    ['22N81A6219', 'BURUGULA YOGESH', 'B SRINIVAS', '2004-08-25', 'A+', '850614000000', '8688756151', 'Sri Ram Nagar Colony, Gowlipura'],
    ['22N81A6220', 'DESAI PRASHANTH MUDIRAJ', 'DESAI LAXMAN', '2003-09-26', 'O+', '460054000000', '8374750051', 'Thirumala Hills, Meerpet'],
    ['22N81A6221', 'KATHIREDDY ABHISREE REDDY', 'KATHIREDDY PANDU REDDY', '2005-06-07', 'O+', '756152000000', '9440848495', 'Sama Saraswathi Colony, Karmanghat'],
    ['22N81A6222', 'VEERAGANDHAM ANKITHA VENKAT', 'V. VENKATA RAO', '2004-02-04', 'B', '328797000000', '8970336666', 'Green City Colony, Nadargul'],
    ['22N81A6223', 'THIRUNAGARI ADITI', 'TR. RAVI KUMAR', '2005-01-19', 'B+', '980918000000', '9848091019', 'Central Bank Colony, LB Nagar'],
    ['22N81A6224', 'BALAGONI SOWMYA', 'BALAGONI BALASWAMY', '2004-08-15', 'O+', '596844000000', '9963007579', 'Nagarjuna Colony, Hastinapuram'],
    ['22N81A6225', 'BOLLEDDU SRI CHANDANA', 'B. NARSING RAO', '2004-11-24', 'B+', '281190000000', '8712192119', 'Adhikari Nagar, Saroornagar'],
    ['22N81A6226', 'N NAGA SAHITHI', 'N PRAVEEN KUMAR', '2004-12-17', 'O+', '935752000000', '8790361463', 'RN Reddy Colony, Meerpet'],
    ['22N81A6227', 'SAMA RESHMA REDDY', 'S PRATHAP REDDY', '2004-04-24', 'O-', '747474000000', '9666751116', 'Kothaguda Village, Kandhukur X Road'],
    ['22N81A6228', 'GUDEPALLY TARUNKUMAR', 'GUDEPALLY ANJAIAH', '2003-05-06', 'O+', '370560000000', '6303193265', 'Siddapur, RR District'],
    ['22N81A6229', 'YENNEDLA AKSHAY REDDY', 'RAVINDER REDDY', '2005-04-04', 'B+', '206623000000', '9440666440', 'Karmanghat'],
    ['22N81A6230', 'DADIGE GOUTHAM', 'DADIGE JITHENDHAR', '2004-02-20', 'O+', '688575000000', '9293113051', 'Nizamabad'],
    ['22N81A6232', 'RAPARTI VIJAY KUMAR', 'RAPARTI VENKTAIAH', '2003-01-09', 'A+', '597125000000', '6301711515', 'Jogigudem'],
    ['22N81A6233', 'PADMA JATIN', 'PADMA YADAGIRI', '2005-05-02', 'B+', '311802000000', '9849599072', 'Kismathpur, Bandlaguda Jagir'],
    ['22N81A6234', 'JAKKARTHI PRIYATHAMAN', 'J. SRISALIAM', '2005-04-11', 'B+', '354533000000', '9866423360', 'Mallikarjun Nagar, Nagole'],
    ['22N81A6235', 'ADI SAGAR', 'ADI SRINIVASULU', '2003-03-19', 'B+', '482422000000', '7337598379', 'NGOs Colony, Vanasthalipuram'],
    ['22N81A6236', 'BEJJARAPU VINAYKUMAR', 'BEJJARAPU RAMCHANDHAR', '2003-10-26', 'O+', '444954000000', '9550180651', 'Ameer Nagar, Nizamabad'],
    ['22N81A6237', 'JATOTH MANOJ KUMAR', 'ATHMARAM', '2004-03-02', 'B+', '680185000000', '9440709752', 'Anarpally, Asifabad'],
    ['22N81A6239', 'RAJAN SINGH DEORA', 'AMAR SINGH DEORA', '2002-10-30', 'O+', '752636000000', '8317644071', 'Gowliguda Chaman, Osman Shahi, Hyderabad'],
    ['22N81A6240', 'VEDA YESHWANTH KUMAR', 'CH. SUDHAKAR', '2004-03-27', 'O+', '728578000000', '8341665518', 'Vijay Nagar Colony, Karmanghat'],
    ['22N81A6241', 'KARNATI SHESHANTH REDDY', 'KARNATI VEMA REDDY', '2004-10-13', 'O+', '802603000000', '9676828628', 'Indraprastha Colony, Hasthinapuram'],
    ['22N81A6242', 'CHEVVA HEMALATHA', 'CHEVVA RAVINDER', '2005-06-10', 'O+', '924569000000', '7382807952', 'Gungal, Yacharam, Ranga Reddy'],
    ['22N81A6243', 'KOMMINENI DEEKSHITHA', 'KOMMINENI CHALAPATHI RAO', '2005-05-04', '', '914813000000', '6300331663', 'Rajugudem Village, Tiruvuru District'],
    ['22N81A6244', 'CHALAMALASETTY MEGHANA', 'CH. SATYANARAYANA', '2005-06-13', 'B+', '992434000000', '7287848781', 'Pavanpuri Colony, Karmanghat'],
    ['22N81A6245', 'GARLAPATI BINDU', 'GARLAPATI CHANDA SHEKAR', '2004-02-16', 'B+', '630161000000', '9848635227', 'Prashanth Nagar, Vanasthalipuram'],
    ['22N81A6246', 'SHATI KRISHNASREE', 'SHYAM SUNDARACHARYA', '2005-06-25', 'O+', '455229000000', '8341660062', 'Padma Nagar Colony, Karmanghat'],
    ['22N81A6247', 'KATOOGI SRIHARSHITHA', 'KATOOGI SRINIVASA CHARY', '2004-12-14', 'B+', '521404000000', '8309975359', 'Sai Nagar Colony, Balapur X Roads'],
    ['22N81A6248', 'GANDRA ASHWITHA', 'GANDRA THIRUPATHI RAO', '2004-06-06', 'O+', '411137000000', '9912970174', 'Rampally, Peddapalli'],
    ['22N81A6249', 'UPPUGALLA JAYANTH', 'UPPUGALLA SRINIVAS', '2003-09-07', 'O+', '362749000000', '9160895900', 'Vempet, Metpally, Jagityal'],
    ['22N81A6250', 'DODDI MANI CHAKRA DEEP', 'D. NAGESHWAR RAO', '2005-07-18', 'A+', '755489000000', '9493133750', 'Balaji Residency Phase 2, Nadergul'],
    ['22N81A6251', 'GONURU MANIDEEP', 'GONURU CHANDRA SHEKAR', '2004-10-23', 'O+', '230670000000', '9948930942', 'Mazeed Road, Wanaparthy'],
    ['22N81A6252', 'KESHABOINA RAGHU', 'KESHABOINA UMESH', '2003-09-24', 'O+', '374187000000', '9959660937', 'Thatikol, Devarakonda'],
    ['22N81A6253', 'PASHIKANTI TEJA', 'PASHIKANTI VENKATESHAM', '2004-02-24', 'O+', '982959000000', '9666737616', 'Gajwel, Near Yadagiri Theatre'],
    ['22N81A6254', 'LINGALA BISHMA GOUD', 'LINGALA GNANESHWAR GOUD', '2004-11-16', '', '572023000000', '9182981209', 'Ramanthapur, Hyderabad'],
    ['22N81A6255', 'NAGAPURI PRAVEEN', 'NAGAPURI CHANDRAIAH', '2004-03-28', 'O+', '367154000000', '7093138356', 'Golnaka, Amberpet, Hyderabad'],
    ['22N81A6256', 'DAMARLA GOUTHAM', 'D KRISHNAIAH', '2004-11-26', 'O+', '621218000000', '8247054524', 'Omkar Nagar, Hasthinapuram'],
    ['22N81A6257', 'KORABANDY TEJODHAR', 'KORABANDY SHYAMSUNDAR', '2003-01-26', 'B+', '899435000000', '8985926891', 'Jai Suryapatnam, Nadergul'],
    ['22N81A6258', 'RAMAVATH KANIF NAIK', 'RAMAVATH PANDU NAIK', '2003-02-16', 'O+', '592740000000', '9666225418', 'Sridhar Colony, Jilluguda'],
    ['22N81A6260', 'NAKKA VINAY KUMAR GOUD', 'NAKKA RAJU GOUD', '2004-05-02', 'O+', '750224000000', '9652676923', 'Munaganoor, Abdullapurmet'],
    ['22N81A6261', 'BALAM DILEEP', 'B. SATISH', '2005-04-08', '', '612278000000', '8328134448', 'Central Bank Colony, LB Nagar'],
    ['22N81A6262', 'NAKEERTHA VENKATARAMANA', 'SRIKANTH', '2005-06-16', 'B+', '637345000000', '8686096319', 'Hariharapuram, Vanasthalipuram'],
    ['22N81A6263', 'MEDIPELLY SAIGANESH', 'RAMAKRISHNNA', '2005-07-27', 'O+', '803598000000', '9652539636', 'Nakerkal'],
    ['22N81A6264', 'GONEMONI VAISHNAVI', 'GONEMONI KUMAR', '2003-05-10', 'O+', '901817000000', '9396811666', 'Ravirala Village, Maheshwaram'],
    ['22N81A6265', 'ADDELA KAVYA REDDY', 'Addela Krishna Reddy', '2005-01-21', 'O+', '711940000000', '9030814959', 'Balaji Nagar, Hayathnagar'],
    ['22N81A6266', 'DUBBANMARDI SHRAVANI', 'DUBBANMARDI KIRAN', '2004-09-02', 'AB+', '621863000000', '8328111526', 'Joshiwadi, Begum Bazar'],
    ['22N81A6267', 'KOMPALLY MANISHA', 'KOMPALLY RAMESH', '2005-06-09', 'O+', '410218000000', '8341314259', 'Gokhale Nagar, Ramanthapur'],
    // CS-B Section starts here
    ['22N81A6269', 'GARLAPALLY MAMATHA', 'GARLAPALLY SRISAILAM', '2004-06-17', 'B+', '779433000000', '9347981938', 'Lalitha Nagar, Jillelaguda'],
    ['22N81A6270', 'KANCHARLA KAVYA', 'KANCHARLA PRASAD', '2003-06-03', 'A+', '485125000000', '7095157474', 'Chinthalapalem, Zarugumalli, Prakasam District'],
    ['22N81A6271', 'DEBBATI NAVEEN', 'D. SRINIVAS', '2004-09-14', 'B+', '736096000000', '6305021056', 'Sirpur Kagaznagar, Guntlapet'],
    ['22N81A6272', 'GUTHA ABHINAY REDDY', 'GUTHA UPENDER REDDY', '2004-11-27', 'O-', '733506000000', '7780439387', 'Balaji Nagar, Chityala, Nalgonda District'],
    ['22N81A6273', 'GADDAM KRISHNA CHAITANYA', 'GADDAM KRISHNA GOUD', '2004-02-11', 'B+', '862387000000', '8686839993', 'Malkajgiri'],
    ['22N81A6274', 'AREGONI PAVAN KUMAR', 'AREGONI JAGANNADHAM', '2005-04-10', 'O+', '931879000000', '9951667649', 'Hastinapuram'],
    ['22N81A6275', 'MOGILI VINEETH KUMAR', 'MOGILI MALLIKARJUNA', '2005-01-05', 'B+', '243643000000', '7989806035', 'Meerpet'],
    ['22N81A6276', 'MUMMIDI DEEPTHI', 'MUMMIDI SRINIVAS', '2004-07-30', 'B+', '518994000000', '8520055943', 'Green Hills Colony, Karmanghat'],
    ['22N81A6277', 'PEDADA SRI LAXMI', 'PEDADA SHANKAR', '2005-05-25', 'B+', '537892000000', '9885031879', 'Meerpet, Hyderabad'],
    ['22N81A6278', 'CHINTALA MANIKANTA', 'CHINTALA LINGAIAH', '2004-04-28', 'B+', '892318000000', '8639200663', 'Vepur, Yadadri Bhuvanagiri'],
    ['22N81A6279', 'RAMPURE MANISH', 'RAMPURE RAMESH', '2005-05-03', 'B+', '986661000000', '9848103653', 'Mahabubabad'],
    ['22N81A6280', 'CHINTADA MEGHANA', 'CHINTADA VIJAY KUMAR', '2004-04-23', 'A+', '798190000000', '8919112764', 'Saroor Nagar'],
    ['22N81A6281', 'KODI PRAVALLIKA', 'KODI SRINIVAS', '2004-05-16', 'O+', '870920000000', '9848121285', 'Balapur'],
    ['22N81A6282', 'CHITTEM SRAVANI', 'CHITTEM YADAIAH', '2004-07-17', 'B+', '332316000000', '9704813898', 'Khammam'],
    ['22N81A6283', 'KATKURI TEJASWINI', 'KATKURI LAXMA REDDY', '2005-06-27', 'O+', '764999000000', '9704039064', 'Hayathnagar'],
    ['22N81A6284', 'SHAIK SHABANA', 'SHAIK MOHD MAHABOOB', '2004-07-19', 'O+', '497285000000', '9966258134', 'Santoshnagar'],
    ['22N81A6285', 'SAI SRI LAKSHMI VANGA', 'VANGA SHANKAR RAO', '2005-05-13', 'B+', '918091000000', '9390626526', 'LB Nagar'],
    ['22N81A6286', 'POOSAPATI SAI TARUN', 'POOSAPATI SIVA PRASAD', '2003-11-20', 'B+', '858560000000', '9502961433', 'Ibrahimpatnam'],
    ['22N81A6287', 'BANDA SHIVA KUMAR', 'BANDA SRINIVAS', '2004-04-04', 'O+', '406048000000', '8688325373', 'BHEL, Lingampally'],
    ['22N81A6288', 'KOMMU SAHITHI', 'KOMMU RAMESH', '2005-01-10', 'A+', '775350000000', '8341382220', 'Nagole'],
    ['22N81A6289', 'CHAPPIDI AJAY', 'CHAPPIDI MURALI', '2005-04-17', 'O+', '830765000000', '8639319521', 'LB Nagar'],
    ['22N81A6290', 'CHEEKATI YASHWANTH', 'CHEEKATI VENKATESHWARLU', '2004-10-10', 'B+', '882663000000', '9573471323', 'Meerpet'],
    ['22N81A6291', 'NIMMALA RAGHAVENDRA', 'NIMMALA SRINIVAS', '2004-04-04', 'O+', '790350000000', '7097891256', 'Vanasthalipuram'],
    ['22N81A6292', 'VADITHYA ANUSHA', 'VADITHYA MAHESH', '2004-01-01', 'B+', '548265000000', '9347237912', 'Narsapur, Medak District'],
    ['22N81A6293', 'SIRIGIRI RISHITHA', 'SIRIGIRI SAMPATH KUMAR', '2004-06-10', 'O+', '251932000000', '8374442506', 'Karmanghat'],
    ['22N81A6294', 'SHAIK MOHAMMED IRFAN', 'SHAIK MAHABOOB VALI', '2004-08-25', 'O+', '396108000000', '9700152034', 'Malakpet'],
    ['22N81A6295', 'MOHAMMED MAHBOOB ALI', 'MOHAMMED ABDUL RAZZAK', '2003-10-20', 'B+', '234161000000', '8978499993', 'Kurmaguda, Hyderabad'],
    ['22N81A6296', 'SHAIK ASLAM', 'SHAIK ISMAIL', '2005-02-12', 'B+', '836863000000', '8121671894', 'Jalpally'],
    ['22N81A6297', 'MD AKRAM', 'MD MASTHAN', '2005-03-10', 'O+', '763822000000', '8688350360', 'Mallapur'],
    ['22N81A6298', 'MD SAIF', 'MD RAZA', '2005-07-11', 'B+', '313696000000', '9959428532', 'Yakutpura, Hyderabad'],
    ['22N81A6299', 'MD KALEEM', 'MD OMER', '2005-07-28', 'O+', '380574000000', '8919942085', 'Fateh Darwaza, Hyderabad'],
    ['22N81A62A0', 'MD ZIYA UR RAHMAN', 'MD ASHFAQUE', '2003-12-06', 'O+', '821449000000', '7989198612', 'Ghazi Banda, Hyderabad'],
    ['22N81A62A1', 'SINGIRERDDY MANOJ REDDY', 'SINGIRERDDY SRINIVAS REDDY', '2004-02-03', 'B', '781690000000', '9392959065', 'Koheda, Hythnagar, RR'],
    ['22N81A62A2', 'MANNE HEMANTH', 'MANNE NARESH', '2004-12-29', 'O+', '561153732920', '9177209354', 'Kismathpur, Rajendranagar'],
    ['22N81A62A3', 'BACHA KARTHIK', 'B NARSIMHA', '2003-11-11', 'O+', '843651000000', '9032598461', 'Srinivasa Residency, Chaitanyapuri, Dilsukhnagar'],
    ['22N81A62A4', 'ATHINARAPU RAMCHARAN THEJA', 'A. MANYAM KONDA', '2004-08-29', 'O+', '929108000000', '9666138499', 'Nagarkurnool'],
    ['22N81A62A5', 'GOLLENA ADITH', 'GOLLENA ARAVIND KUMAR', '2004-01-16', 'B+', '243555000000', '9063256611', 'Vivek Nagar, Jillelguda'],
    ['22N81A62A6', 'N JASHWANTH', 'N ASHOK', '2004-05-10', '', '527223000000', '9030026928', 'NTR Nagar, Hyderabad'],
    ['22N81A62A7', 'MAHESH BHATI', 'CHHOTMAL BHATI', '2003-10-21', 'B+', '801537156855', '7023138851', 'Sri Sri Ram Nagar, Turkayamjal'],
    ['22N81A62A8', 'PASUNUTI SINDHU', 'PASUNUTI SATISH BABU', '2004-08-03', 'O+', '430340981824', '9908719571', 'P.V. Colony, Manuguru, Bhadradri'],
    ['22N81A62A9', 'KUNDURTHI SAI TEJASWINI', 'KUNDURTHI SRINIVAS RAO', '2004-07-22', 'O+', '933570000000', '6300625654', 'Sri Sai Balaji Homes, Badangpet, RR'],
    ['22N81A62B0', 'SHAIK MOHAMMED ABDUL HAFEEZ', 'SHAIK SHAHABUDDIN', '2004-06-06', 'O+', '253227000000', '7416420889', 'Fateh Darwaza, Hyderabad'],
    ['22N81A62B4', 'SUKKAMETI GOUTHAM REDDY', 'SUKKAMETI VENKATESHWAR REDDY', '2004-07-06', 'B+', '847874000000', '9951512551', 'Santoshnagar, near Bharat Garden'],
    ['22N81A62B5', 'K SRIKANTH REDDY', 'K SAI REDDY', '2004-08-23', 'B+', '404791000000', '6302638374', 'Champapet, Hyderabad'],
    ['22N81A62B6', 'MADIPADIGA KOUSHEEL', 'MADIPADIGA SRINIVAS', '2005-07-12', 'O+', '296605000000', '9291355154', 'Hastinapuram'],
    ['22N81A62B7', 'KANCHUKATLA DEEPAK', 'KANCHUKATLA RAVINDER', '2004-07-10', 'B+', '571039000000', '9393662155', 'B. N. Reddy Nagar'],
    ['22N81A62B8', 'MADURI SRINIVASA CHARY', 'MADURI BRAHMA CHARY', '2004-01-22', 'AB+', '313879000000', '9642031549', 'Uppal, Bharat Nagar Colony'],
    ['22N81A62B9', 'JANNI DON KARTHIK', 'JANNI MAHESH', '2004-10-09', 'O+', '948969000000', '9381204722', 'Turkayamjal'],
    ['22N81A62C0', 'KALIBIRIKI DINESH KARTHIK', 'KALIBIRIKI SRINIVASULU', '2005-02-15', 'O+', '746375000000', '6281380110', 'Vankeshwaram, Padara, Nagarkurnool'],
    ['22N81A62C1', 'VASAMSHETTI PRASHANTH', 'VASAMSHETTI DEVENDAR', '2002-02-25', 'O+', '405661000000', '9010682718', 'Akaram (V), Shaligouraram (M), Nalgonda'],
    ['22N81A62C2', 'NENAVATH PRAKASH', 'NENAVATH LOKYA', '2003-12-06', 'B+', '633131000000', '7901052320', 'Mallapur, Balapur'],
    ['22N81A62C5', 'SHAIK MOHAMMED BILAL', 'SHAIK DASTAGIRI', '2003-07-24', 'B+', '884582000000', '9703097267', 'B. N. Reddy Nagar, Thirumala Nagar'],
    ['22N81A62C6', 'MACHARAM HARSHAVARDHAN REDDY', 'MACHARAM RAGHAVENDER REDDY', '2005-10-31', 'O', '774677000000', '7013519296', 'Narayanguda, Hyderabad'],
    ['22N81A62C7', 'S BHARATH KUMAR', 'S ANJANEYULU', '2005-04-05', '', '317342000000', '9492169219', 'Kazipur, Koilkonda, Mahabubnagar'],
    ['22N81A62C8', 'RAJ GOEL', 'HARISH KUMAR GOEL', '2005-03-28', 'O+', '235019000000', '9885096814', 'Shalivahana Nagar, Dilsukhnagar']
];

// Array to store login credentials for output
$login_credentials = [];

// Process each student
foreach ($students_data as $student) {
    $roll_number = $student[0];
    $full_name = $student[1];
    $father_name = $student[2];
    $dob = $student[3];
    $blood_group = $student[4];
    $aadhaar_number = $student[5];
    $phone_number = $student[6];
    $address = $student[7];

    // Generate email and password
    $email = generateEmail($full_name);
    $password = generatePassword($roll_number);
    // Use plain text password for easier testing
    $plain_password = $password;

    // Determine section
    $section = getSection($roll_number);

    // Generate username from name
    $username = strtolower(str_replace([' ', '.', ','], ['_', '', ''], $full_name));

    // Set default values
    $department = 'Cyber Security';
    $year = 3; // 3rd year for 2022 batch
    $semester = '1st';
    $course = 'Cyber Security';
    $program = 'B.Tech';
    $batch = '2022';

    // Check if student already exists
    $check = $conn->query("SELECT id FROM students WHERE roll_number = '$roll_number'");
    if ($check->num_rows == 0) {
        // Insert student
        $stmt = $conn->prepare("INSERT INTO students (username, full_name, email, password, roll_number, father_name, dob, blood_group, aadhaar_number, phone_number, address, section, department, year, semester, course, program, batch, student_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $student_id = 'STU' . substr($roll_number, -6); // Use last 6 characters of roll number

        $stmt->bind_param("ssssssssssssssissss",
            $username, $full_name, $email, $plain_password, $roll_number,
            $father_name, $dob, $blood_group, $aadhaar_number, $phone_number,
            $address, $section, $department, $year, $semester, $course, $program, $batch, $student_id
        );

        if ($stmt->execute()) {
            echo "<li>Added student: $full_name (Roll: $roll_number, Section: $section)</li>";

            // Store login credentials
            $login_credentials[] = [
                'roll_number' => $roll_number,
                'name' => $full_name,
                'email' => $email,
                'password' => $password,
                'section' => $section
            ];

            // Mark roll number as used
            $conn->query("UPDATE roll_numbers SET is_used = TRUE, used_by_student_id = LAST_INSERT_ID(), used_at = NOW() WHERE roll_number = '$roll_number'");
        } else {
            echo "<li style='color: red;'>Error adding student $full_name: " . $stmt->error . "</li>";
        }
        $stmt->close();
    } else {
        echo "<li style='color: orange;'>Student with roll number $roll_number already exists</li>";
    }
}

echo "<h3>Creating Class Schedules for Both Sections...</h3>";

// Add schedule for CS-B section (copy from CS-A and modify)
$schedule_result = $conn->query("SELECT * FROM schedules WHERE section = 'CS-A'");
if ($schedule_result->num_rows > 0) {
    while ($schedule = $schedule_result->fetch_assoc()) {
        // Check if CS-B schedule already exists for this time slot
        $check = $conn->query("SELECT id FROM schedules WHERE section = 'CS-B' AND day_of_week = '{$schedule['day_of_week']}' AND period_id = {$schedule['period_id']}");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO schedules (section, room_number, day_of_week, period_id, subject_id, faculty_id, lab_group, lab_location, is_lab, effective_from) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $section_name = 'CS-B';
            $room_number = '308'; // Different room for CS-B
            $stmt->bind_param("sssiiiisss",
                $section_name, $room_number, $schedule['day_of_week'], $schedule['period_id'],
                $schedule['subject_id'], $schedule['faculty_id'], $schedule['lab_group'],
                $schedule['lab_location'], $schedule['is_lab'], $schedule['effective_from']
            );
            if ($stmt->execute()) {
                echo "<li>Added CS-B schedule: {$schedule['day_of_week']} Period {$schedule['period_id']}</li>";
            }
            $stmt->close();
        }
    }
}

echo "<h2>Login Credentials for All New Students</h2>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>CS-A Section (Roll Numbers: 22N81A6201-22N81A6267)</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
echo "<tr style='background: #007bff; color: white;'><th>Roll Number</th><th>Name</th><th>Email</th><th>Password</th></tr>";

foreach ($login_credentials as $cred) {
    if ($cred['section'] == 'CS-A') {
        echo "<tr>";
        echo "<td>{$cred['roll_number']}</td>";
        echo "<td>{$cred['name']}</td>";
        echo "<td>{$cred['email']}</td>";
        echo "<td>{$cred['password']}</td>";
        echo "</tr>";
    }
}
echo "</table>";

echo "<h3>CS-B Section (Roll Numbers: 22N81A6268-22N81A62C8)</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%; margin-bottom: 20px;'>";
echo "<tr style='background: #28a745; color: white;'><th>Roll Number</th><th>Name</th><th>Email</th><th>Password</th></tr>";

foreach ($login_credentials as $cred) {
    if ($cred['section'] == 'CS-B') {
        echo "<tr>";
        echo "<td>{$cred['roll_number']}</td>";
        echo "<td>{$cred['name']}</td>";
        echo "<td>{$cred['email']}</td>";
        echo "<td>{$cred['password']}</td>";
        echo "</tr>";
    }
}
echo "</table>";
echo "</div>";

echo "<h3>Summary</h3>";
echo "<ul>";
echo "<li>Total students added: " . count($login_credentials) . "</li>";
echo "<li>CS-A Section: " . count(array_filter($login_credentials, function($c) { return $c['section'] == 'CS-A'; })) . " students</li>";
echo "<li>CS-B Section: " . count(array_filter($login_credentials, function($c) { return $c['section'] == 'CS-B'; })) . " students</li>";
echo "<li>All students can now login using their email and password</li>";
echo "<li>Sections have been created with appropriate room assignments (CS-A: Room 307, CS-B: Room 308)</li>";
echo "</ul>";

$conn->close();
?>
