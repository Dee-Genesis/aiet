<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// ── Load PHPMailer ──
function loadPHPMailer() {
    $composerAutoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($composerAutoload)) {
        require $composerAutoload;
        return true;
    }
    $dir   = __DIR__ . '/phpmailer/';
    $files = ['Exception.php', 'PHPMailer.php', 'SMTP.php'];
    $allExist = true;
    foreach ($files as $f) { if (!file_exists($dir . $f)) { $allExist = false; break; } }

    if (!$allExist) {
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $base = 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/';
        foreach ($files as $f) {
            $dest = $dir . $f;
            if (!file_exists($dest)) {
                $c = @file_get_contents($base . $f);
                if ($c) file_put_contents($dest, $c);
            }
        }
    }
    foreach ($files as $f) {
        $p = $dir . $f;
        if (file_exists($p)) require_once $p;
        else return false;
    }
    return true;
}

$phpMailerLoaded = loadPHPMailer();

// ── Sanitise ──
function clean($v) { return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8'); }

// All form fields
$program       = clean($_POST['programSelect']   ?? '');
$spec          = clean($_POST['specialization']  ?? '');
$intakeYear    = clean($_POST['intakeYear']       ?? '');
$intakeSem     = clean($_POST['intakeSem']        ?? '');
$studyMode     = clean($_POST['studyMode']        ?? '');
$referral      = clean($_POST['referralSource']   ?? '');
$title         = clean($_POST['title']            ?? '');
$firstName     = clean($_POST['firstName']        ?? '');
$lastName      = clean($_POST['lastName']         ?? '');
$middleName    = clean($_POST['middleName']        ?? '');
$dob           = clean($_POST['dob']              ?? '');
$gender        = clean($_POST['gender']           ?? '');
$nationality   = clean($_POST['nationality']      ?? '');
$passportNum   = clean($_POST['passportNum']      ?? '');
$country       = clean($_POST['country']          ?? '');
$email         = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$phone         = clean($_POST['phone']            ?? '');
$whatsapp      = clean($_POST['whatsapp']         ?? '');
$address       = clean($_POST['address']          ?? '');
$highestQual   = clean($_POST['highestQual']      ?? '');
$fieldStudy    = clean($_POST['fieldOfStudy']     ?? '');
$institution   = clean($_POST['institution1']     ?? '');
$instCountry   = clean($_POST['instCountry1']     ?? '');
$gradYear      = clean($_POST['gradYear']         ?? '');
$grade         = clean($_POST['grade']            ?? '');
$instrLang     = clean($_POST['instrLang']        ?? '');
$qual2         = clean($_POST['qual2']            ?? '');
$body2         = clean($_POST['body2']            ?? '');
$englishMethod = clean($_POST['englishMethod']    ?? '');
$englishScore  = clean($_POST['englishScore']     ?? '');
$englishDate   = clean($_POST['englishDate']      ?? '');
$jobTitle      = clean($_POST['jobTitle']         ?? '');
$employer      = clean($_POST['employer']         ?? '');
$industry      = clean($_POST['industry']         ?? '');
$yearsExp      = clean($_POST['yearsExp']         ?? '');
$mgmtExp       = clean($_POST['mgmtExp']          ?? '');
$motivation    = clean($_POST['motivation']       ?? '');
$goals         = clean($_POST['goals']            ?? '');
$ref1name      = clean($_POST['ref1name']         ?? '');
$ref1email     = clean($_POST['ref1email']        ?? '');
$ref1title     = clean($_POST['ref1title']        ?? '');
$ref1org       = clean($_POST['ref1org']          ?? '');
$ref           = clean($_POST['ref']              ?? '');

if (!$firstName || !$lastName || !$email || !$program || !$ref) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// ── Upload directory ──
$uploadDir = __DIR__ . '/uploads/applications/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

function handleUpload($fieldName, $uploadDir, $ref, $maxBytes, $allowedExts) {
    if (empty($_FILES[$fieldName]['name'])) return ['ok' => false, 'path' => null, 'origName' => null];
    $file    = $_FILES[$fieldName];
    $ext     = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
    $newName = $ref . '_' . $fieldName . '.' . $ext;
    $dest    = $uploadDir . $newName;
    if ($file['error'] !== UPLOAD_ERR_OK)     return ['ok' => false, 'path' => null, 'origName' => $file['name']];
    if ($file['size'] > $maxBytes)            return ['ok' => false, 'path' => null, 'origName' => $file['name']];
    if (!in_array($ext, $allowedExts))        return ['ok' => false, 'path' => null, 'origName' => $file['name']];
    if (!move_uploaded_file($file['tmp_name'], $dest)) return ['ok' => false, 'path' => null, 'origName' => $file['name']];
    return ['ok' => true, 'path' => $dest, 'name' => $newName, 'origName' => $file['name']];
}

$imgExts = ['pdf','jpg','jpeg','png'];
$docExts = ['pdf','doc','docx'];

$uploads = [
    'upload_id'          => handleUpload('upload_id',          $uploadDir, $ref, 5*1024*1024, $imgExts),
    'upload_transcripts' => handleUpload('upload_transcripts', $uploadDir, $ref, 5*1024*1024, $imgExts),
    'upload_cv'          => handleUpload('upload_cv',          $uploadDir, $ref, 5*1024*1024, $docExts),
    'upload_english'     => handleUpload('upload_english',     $uploadDir, $ref, 5*1024*1024, $imgExts),
    'upload_refs'        => handleUpload('upload_refs',        $uploadDir, $ref, 5*1024*1024, $imgExts),
];

$labels = [
    'upload_id'          => 'Passport / ID',
    'upload_transcripts' => 'Academic Transcripts',
    'upload_cv'          => 'CV / Resume',
    'upload_english'     => 'English Certificate',
    'upload_refs'        => 'Reference Letters',
];

// ── Email content ──
$fullName = trim("$title $firstName $middleName $lastName");
$now      = date('l, d F Y \a\t H:i T');
$intake   = "$intakeSem $intakeYear";
$subject  = "New Application | $program | $firstName $lastName [$ref]";

$nl   = "\r\n";
$body  = "AMERICAN INSTITUTE OF EDUCATION AND TRAINING — ONLINE APPLICATION{$nl}";
$body .= "=========================================={$nl}";
$body .= "Reference Number : {$ref}{$nl}";
$body .= "Submitted On     : {$now}{$nl}{$nl}{$nl}";

$body .= "[ 1 ] PROGRAM SELECTION{$nl}";
$body .= "------------------------{$nl}";
$body .= "Program Applied For  : {$program}{$nl}";
$body .= "Specialization       : " . ($spec ?: 'Not specified') . "{$nl}";
$body .= "Intake               : {$intake}{$nl}";
$body .= "Study Mode           : {$studyMode}{$nl}";
$body .= "Referral Source      : " . ($referral ?: 'Not provided') . "{$nl}{$nl}{$nl}";

$body .= "[ 2 ] PERSONAL INFORMATION{$nl}";
$body .= "---------------------------{$nl}";
$body .= "Full Name            : {$fullName}{$nl}";
$body .= "Date of Birth        : {$dob}{$nl}";
$body .= "Gender               : {$gender}{$nl}";
$body .= "Nationality          : {$nationality}{$nl}";
$body .= "ID / Passport No.    : {$passportNum}{$nl}";
$body .= "Country of Residence : {$country}{$nl}";
$body .= "Email Address        : {$email}{$nl}";
$body .= "Phone Number         : {$phone}{$nl}";
$body .= "WhatsApp             : " . ($whatsapp ?: 'Same as phone') . "{$nl}";
$body .= "Mailing Address      : {$address}{$nl}{$nl}{$nl}";

$body .= "[ 3 ] ACADEMIC BACKGROUND{$nl}";
$body .= "--------------------------{$nl}";
$body .= "Highest Qualification    : {$highestQual}{$nl}";
$body .= "Field of Study           : {$fieldStudy}{$nl}";
$body .= "Institution              : {$institution}{$nl}";
$body .= "Country of Institution   : {$instCountry}{$nl}";
$body .= "Year of Graduation       : {$gradYear}{$nl}";
$body .= "Grade / GPA              : " . ($grade ?: 'Not provided') . "{$nl}";
$body .= "Language of Instruction  : {$instrLang}{$nl}";
$body .= "Additional Qualification : " . ($qual2 ?: 'None') . "{$nl}";
$body .= "Awarding Body            : " . ($body2 ?: 'N/A') . "{$nl}";
$body .= "English Proficiency      : {$englishMethod}{$nl}";
$body .= "English Test Score       : " . ($englishScore ?: 'N/A') . "{$nl}";
$body .= "English Test Date        : " . ($englishDate ?: 'N/A') . "{$nl}{$nl}{$nl}";

$body .= "[ 4 ] PROFESSIONAL EXPERIENCE & GOALS{$nl}";
$body .= "--------------------------------------{$nl}";
$body .= "Current Job Title        : " . ($jobTitle ?: 'Not provided') . "{$nl}";
$body .= "Current Employer         : " . ($employer ?: 'Not provided') . "{$nl}";
$body .= "Industry / Sector        : " . ($industry ?: 'Not provided') . "{$nl}";
$body .= "Years of Work Experience : " . ($yearsExp ?: 'Not provided') . "{$nl}";
$body .= "Years in Management      : " . ($mgmtExp ?: 'Not provided') . "{$nl}{$nl}";
$body .= "STATEMENT OF PURPOSE:{$nl}";
$body .= "---------------------{$nl}";
$body .= ($motivation ?: 'Not provided') . "{$nl}{$nl}";
$body .= "CAREER GOALS:{$nl}";
$body .= "-------------{$nl}";
$body .= ($goals ?: 'Not provided') . "{$nl}{$nl}{$nl}";

$body .= "[ 5 ] REFERENCE{$nl}";
$body .= "----------------{$nl}";
$body .= "Name         : " . ($ref1name  ?: 'Not provided') . "{$nl}";
$body .= "Email        : " . ($ref1email ?: 'Not provided') . "{$nl}";
$body .= "Position     : " . ($ref1title ?: 'Not provided') . "{$nl}";
$body .= "Organisation : " . ($ref1org   ?: 'Not provided') . "{$nl}{$nl}{$nl}";

$body .= "[ 6 ] UPLOADED DOCUMENTS{$nl}";
$body .= "-------------------------{$nl}";
foreach ($uploads as $key => $up) {
    $label = $labels[$key] ?? $key;
    $body .= str_pad($label . ' :', 25) . ($up['ok'] ? $up['origName'] . ' (attached)' : 'Not uploaded') . "{$nl}";
}
$body .= "{$nl}=========================================={$nl}";
$body .= "Files also saved on server: uploads/applications/{$nl}";
$body .= "Contact applicant: {$email}{$nl}";
$body .= "Reference: {$ref}{$nl}";

// ── Send email ──
$sent    = false;
$warning = '';

if ($phpMailerLoaded) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSendmail();
        $mail->setFrom('noreply@aietglobal.us', 'AIET Admissions Portal');
        $mail->addAddress('info@aietglobal.us', 'AIET Admissions');
        $mail->addReplyTo($email, $fullName);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->isHTML(false);

        // Attach all uploaded files
        foreach ($uploads as $up) {
            if ($up['ok'] && $up['path'] && file_exists($up['path'])) {
                $mail->addAttachment($up['path'], $up['origName']);
            }
        }

        $mail->send();
        $sent = true;

    } catch (\Exception $e) {
        $warning         = 'PHPMailer: ' . $e->getMessage();
        $phpMailerLoaded = false;
    }
}

if (!$sent) {
    // Fallback: basic mail() without attachments
    $headers  = "From: noreply@aietglobal.us\r\n";
    $headers .= "Reply-To: {$email}\r\n";
    $fallbackNote = "\r\n\r\nNOTE: File attachments failed. Retrieve from server: uploads/applications/\r\n" . ($warning ? "Error: $warning" : '');
    $sent = mail('info@aietglobal.us', $subject, $body . $fallbackNote, $headers);
}

echo json_encode([
    'success' => $sent,
    'ref'     => $ref,
    'warning' => $warning ?: null
]);
?>
