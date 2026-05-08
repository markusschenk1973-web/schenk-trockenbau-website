<?php
// Kontaktformular für schenk-trockenbau.de
// Reines PHP mail() ohne externe Bibliotheken, ohne PHPMailer, ohne SMTP.
// Bewusst PHP-5.6-kompatibel geschrieben, damit auch alte Tarife laufen.

// -------------------------------------------------------------------------
// DIAGNOSE — diese drei Zeilen NACH erfolgreichem Test entfernen.
// Sie zeigen jeden PHP-Fehler im Klartext in der Browser-Antwort an.
// -------------------------------------------------------------------------
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// -------------------------------------------------------------------------
// KONFIGURATION
// -------------------------------------------------------------------------
$empfaenger   = 'info@schenk-trockenbau.de';
$absenderMail = 'info@schenk-trockenbau.de';
$absenderName = 'Kontakt Website';
$logDatei     = __DIR__ . '/kontakt-error.log';

// -------------------------------------------------------------------------
// HEALTH-CHECK
// Jeder GET-Aufruf zeigt eine Klartext-Diagnose an. Im Browser öffnen:
//   https://schenk-trockenbau.de/kontakt.php
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "kontakt.php LÄUFT\n";
    echo str_repeat('-', 40) . "\n";
    echo 'PHP-Version:        ' . phpversion() . "\n";
    echo 'mail() vorhanden:   ' . (function_exists('mail') ? 'ja' : 'NEIN') . "\n";
    echo 'Skript-Verzeichnis: ' . __DIR__ . "\n";
    echo 'Verzeichnis schreibbar: ' . (is_writable(__DIR__) ? 'ja' : 'NEIN') . "\n";
    echo 'Logdatei vorhanden: ' . (file_exists($logDatei) ? 'ja (' . filesize($logDatei) . ' Bytes)' : 'nein') . "\n";
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('ok' => false, 'msg' => 'Methode nicht erlaubt.'));
    exit;
}

// -------------------------------------------------------------------------
// HONEYPOT — versteckte Felder werden nur von Bots ausgefüllt
// -------------------------------------------------------------------------
$honey = isset($_POST['website']) ? trim((string)$_POST['website']) : '';
if ($honey !== '') {
    echo json_encode(array('ok' => true, 'msg' => 'Vielen Dank.'));
    exit;
}

// -------------------------------------------------------------------------
// EINGABEN EINLESEN
// -------------------------------------------------------------------------
$vorname   = isset($_POST['vorname'])   ? trim((string)$_POST['vorname'])   : '';
$nachname  = isset($_POST['nachname'])  ? trim((string)$_POST['nachname'])  : '';
$email     = isset($_POST['email'])     ? trim((string)$_POST['email'])     : '';
$telefon   = isset($_POST['telefon'])   ? trim((string)$_POST['telefon'])   : '';
$leistung  = isset($_POST['leistung'])  ? trim((string)$_POST['leistung'])  : '';
$nachricht = isset($_POST['nachricht']) ? trim((string)$_POST['nachricht']) : '';
$dsgvo     = isset($_POST['dsgvo']);

$fehler = array();
if ($vorname === '')                                          { $fehler[] = 'Vorname fehlt.'; }
if ($nachname === '')                                         { $fehler[] = 'Nachname fehlt.'; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL))               { $fehler[] = 'E-Mail-Adresse ungültig.'; }
if ($telefon === '')                                          { $fehler[] = 'Telefonnummer fehlt.'; }
if ($leistung === '')                                         { $fehler[] = 'Bitte eine Leistung auswählen.'; }
if ($nachricht === '')                                        { $fehler[] = 'Nachricht fehlt.'; }
if (!$dsgvo)                                                  { $fehler[] = 'Bitte der Datenschutzerklärung zustimmen.'; }

// Header-Injection-Schutz
if (preg_match('/[\r\n]/', $vorname . $nachname . $email . $telefon . $leistung)) {
    $fehler[] = 'Ungültige Zeichen erkannt.';
}
if (strlen($nachricht) > 5000)                                { $fehler[] = 'Nachricht ist zu lang.'; }
if (strlen($vorname) > 200 || strlen($nachname) > 200)        { $fehler[] = 'Name ist zu lang.'; }

if (count($fehler) > 0) {
    http_response_code(422);
    echo json_encode(array('ok' => false, 'msg' => implode(' ', $fehler)));
    exit;
}

// -------------------------------------------------------------------------
// MAIL ZUSAMMENBAUEN
// -------------------------------------------------------------------------
$betreffKlar = 'Neue Anfrage über schenk-trockenbau.de – ' . $leistung;
$betreff     = '=?UTF-8?B?' . base64_encode($betreffKlar) . '?=';

$body  = "Neue Anfrage über das Kontaktformular\r\n";
$body .= str_repeat('-', 50) . "\r\n\r\n";
$body .= 'Name:      ' . $vorname . ' ' . $nachname . "\r\n";
$body .= 'E-Mail:    ' . $email . "\r\n";
$body .= 'Telefon:   ' . $telefon . "\r\n";
$body .= 'Leistung:  ' . $leistung . "\r\n\r\n";
$body .= "Nachricht:\r\n" . wordwrap($nachricht, 78, "\r\n", true) . "\r\n\r\n";
$body .= str_repeat('-', 50) . "\r\n";
$body .= 'Eingegangen: ' . date('d.m.Y H:i') . "\r\n";
$body .= 'IP:          ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-') . "\r\n";

// Header — bewusst minimal. From muss eine Mailbox auf der eigenen Domain
// sein, damit Alphahosting das Mail-Relay akzeptiert.
$fromHeader = '=?UTF-8?B?' . base64_encode($absenderName) . '?= <' . $absenderMail . '>';

$headers  = 'From: '         . $fromHeader     . "\r\n";
$headers .= 'Reply-To: '     . $email          . "\r\n";
$headers .= 'MIME-Version: ' . '1.0'           . "\r\n";
$headers .= 'Content-Type: ' . 'text/plain; charset=UTF-8' . "\r\n";
$headers .= 'Content-Transfer-Encoding: 8bit';

// -------------------------------------------------------------------------
// VERSAND
// -------------------------------------------------------------------------
$ok = @mail($empfaenger, $betreff, $body, $headers);

if ($ok) {
    echo json_encode(array(
        'ok'  => true,
        'msg' => 'Vielen Dank! Ihre Anfrage wurde gesendet. Wir melden uns in Kürze bei Ihnen.',
    ));
    exit;
}

// Fehlerfall — Diagnose loggen und im JSON zurückgeben
$err  = error_get_last();
$diag = ($err && isset($err['message'])) ? $err['message'] : 'mail() gab false zurück, keine Detail-Meldung des Servers.';

@file_put_contents($logDatei, '[' . date('Y-m-d H:i:s') . '] ' . $diag . "\r\n", FILE_APPEND);

http_response_code(500);
echo json_encode(array(
    'ok'    => false,
    'msg'   => 'Beim Senden ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut oder kontaktieren Sie uns telefonisch unter 0261 – 92163506.',
    'debug' => $diag,
));
