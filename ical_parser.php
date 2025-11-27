<?php
// 1. DÉFINIR TON LIEN ICAL ICI
$ical_url = 'https://www.airbnb.fr/calendar/ical/1290994820482311065.ics?s=e454e1d755e16e197ca90313cf902dac';

// Headers pour la sécurité (CORS) et le format JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 

$ical_content = @file_get_contents($ical_url);

$reserved_dates = [];
$reservations = [];
$current_event = [];
$is_event = false;

if ($ical_content) {
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
                
                if (strpos($property, 'DTSTART') !== false) {
                    $current_event['DTSTART'] = $value;
                } elseif (strpos($property, 'DTEND') !== false) {
                    $current_event['DTEND'] = $value;
                }
            }
        }
    }

    // Traiter les événements et ajuster la date de fin (check-out)
    foreach ($reservations as $event) {
        $start_ical = $event['DTSTART'];
        $end_ical = $event['DTEND'];
        
        $start_date_str = substr($start_ical, 0, 8);
        $end_date_str = substr($end_ical, 0, 8);
        
        $start_date = date('Y-m-d', strtotime($start_date_str));
        $end_date_time = strtotime($end_date_str);
        
        // Retirer un jour à la date de fin (check-out) pour bloquer la dernière nuitée.
        $last_reserved_day = date('Y-m-d', strtotime('-1 day', $end_date_time));
        
        // Ajouter la plage de dates si elle est valide
        if ($last_reserved_day >= $start_date) {
             $reserved_dates[] = [
                'from' => $start_date,
                'to' => $last_reserved_day
             ];
        } else {
             // Cas spécial (séjour d'une seule nuit)
             $reserved_dates[] = [
                'from' => $start_date,
                'to' => $start_date 
             ];
        }
    }
} 

// Renvoyer le tableau final en JSON
echo json_encode($reserved_dates);
?>