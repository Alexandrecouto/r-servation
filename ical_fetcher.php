<?php
// 1. DÉFINIR TON LIEN ICAL ICI
// ⚠️ MODIFIEZ CE LIEN AVEC VOTRE ICAL AIRBNB RÉEL ⚠️
$ical_url = 'https://www.airbnb.fr/calendar/ical/1290994820482311065.ics?s=e454e1d755e16e197ca90313cf902dac';

// Headers pour la sécurité (CORS) et le format JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 

$reserved_ranges = []; 
$reservations = [];
$current_event = [];
$is_event = false;

// --- MODIFICATION : Tentative d'utilisation de cURL pour plus de fiabilité ---
// cURL est la méthode préférée si file_get_contents échoue.
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ical_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); 
    $ical_content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($ical_content === false || $http_code != 200) {
        // Envoie une erreur si cURL échoue
        http_response_code(500);
        echo json_encode(['error' => 'Erreur: Impossible de charger le fichier iCal (cURL, Code: ' . $http_code . ').']);
        exit;
    }
} else {
    // Fallback à file_get_contents si cURL n'est pas disponible
    $ical_content = @file_get_contents($ical_url);

    if ($ical_content === false) {
        // Envoie une erreur si file_get_contents échoue
        http_response_code(500);
        echo json_encode(['error' => 'Erreur: Impossible de charger le fichier iCal (file_get_contents).']);
        exit;
    }
}
// --- FIN MODIFICATION ---

// 2. ANALYSE DU FICHIER ICAL
$lines = explode("\n", $ical_content);

foreach ($lines as $line) {
    $line = trim($line);

    if ($line === 'BEGIN:VEVENT') {
        $is_event = true;
        $current_event = [];
    } elseif ($line === 'END:VEVENT') {
        if ($is_event && isset($current_event['DTSTART']) && isset($current_event['DTEND'])) {
            $reservations[] = $current_event;
        }
        $is_event = false;
    } elseif ($is_event) {
        // Analyse des propriétés
        if (preg_match('/^([^:]+):(.+)$/', $line, $matches)) {
            $property = $matches[1];
            $value = $matches[2];
            
            $clean_property = strtok($property, ';');
            
            if ($clean_property === 'DTSTART') {
                $current_event['DTSTART'] = $value;
            } elseif ($clean_property === 'DTEND') {
                $current_event['DTEND'] = $value;
            }
        }
    }
}

// 3. TRAITEMENT DES RÉSERVATIONS
foreach ($reservations as $event) {
    if (!isset($event['DTSTART']) || !isset($event['DTEND'])) continue;

    $start_ical = $event['DTSTART'];
    $end_ical = $event['DTEND'];
    
    $start_date_str = substr($start_ical, 0, 8);
    $end_date_str = substr($end_ical, 0, 8);
    
    $start_date = date('Y-m-d', strtotime($start_date_str));
    $end_date_time = strtotime($end_date_str);
    
    // La dernière nuit réservée est le jour AVANT le check-out.
    $last_reserved_day = date('Y-m-d', strtotime('-1 day', $end_date_time));
    
    if ($last_reserved_day >= $start_date) {
        $reserved_ranges[] = [
            'from' => $start_date,
            'to' => $last_reserved_day
        ];
    } else {
        // Cas d'une seule nuit
        $reserved_ranges[] = [
            'from' => $start_date,
            'to' => $start_date 
        ];
    }
}

// Renvoyer le tableau final en JSON
echo json_encode($reserved_ranges);
?>
