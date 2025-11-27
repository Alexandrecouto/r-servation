<?php
// =================================================================
// 1. CONFIGURATION ET RÉCUPÉRATION DES DATES ICAL (PHP)
// =================================================================

// ⚠️ MODIFIEZ CE LIEN AVEC VOTRE ICAL AIRBNB RÉEL ⚠️
$ical_url = 'https://www.airbnb.fr/calendar/ical/1290994820482311065.ics?s=e454e1d755e16e197ca90313cf902dac';

// Headers pour la sécurité (CORS) et le format JSON
// Note: Ces headers sont moins critiques dans un fichier unifié, mais ils sont conservés.
header('Content-Type: text/html; charset=utf-8'); 
header('Access-Control-Allow-Origin: *'); 

$ical_content = @file_get_contents($ical_url);

$reserved_ranges = []; 
$reservations = [];
$current_event = [];
$is_event = false;
$sync_status = 'synced';
$sync_message = 'Synchronisé';

if ($ical_content === false) {
    // Échec de la récupération du fichier ICAL
    $sync_status = 'error';
    $sync_message = 'Erreur de lecture iCal';
} else {
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
}

// Encodage des plages de dates pour être directement utilisé par JavaScript
$json_reserved_ranges = json_encode($reserved_ranges);
// =================================================================
// FIN PHP
// =================================================================
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Module de Réservation Pro V3 - Haute Visibilité</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght400;600;700;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #FF385C; /* Rouge Airbnb vibrant */
            --primary-fade: rgba(255, 56, 92, 0.1);
            --primary-super-fade: rgba(255, 56, 92, 0.05);
            --dark: #222222;
            --gray: #717171;
            --success: #008a05; /* Vert pour la promo */
            --shadow: 0 6px 20px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #F0F2F5;
            margin: 0;
            padding: 20px 10px;
            color: var(--dark);
        }

        .container {
            max-width: 950px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        /* --- Header --- */
        header {
            padding: 20px;
            text-align: center;
            background: #fff;
            border-bottom: 2px solid #f0f0f0;
        }
        h1 { margin: 0; font-size: 1.4rem; font-weight: 800; }
        
        .status-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-badge.loading { color: #b45309; background: #fffbeb; }
        .status-badge.synced { color: #047857; background: #ecfdf5; }
        .status-badge.error { color: #be123c; background: #fff1f2; }

        /* --- Layout Grille --- */
        .content-grid {
            display: flex;
            flex-direction: column;
        }
        @media(min-width: 768px) {
            .content-grid {
                flex-direction: row;
                align-items: stretch;
            }
        }

        /* --- Colonne Gauche : Calendrier --- */
        .calendar-section {
            padding: 25px;
            flex: 3; 
            display: flex;
            justify-content: center;
            align-items: flex-start;
            background: #fff;
        }

        /* --- Colonne Droite : Contrôles & Totaux --- */
        .controls-section {
            padding: 10px;
            flex: 2;
            background-color: #fff;
            display: flex;
            flex-direction: column;
            border-left: 2px solid #f0f0f0;
        }
        
        /* Styles Flatpickr personnalisés */
        .flatpickr-calendar { 
            box-shadow: none !important; 
            width: 100% !important;
        }
        .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange {
            background: var(--primary) !important; border-color: var(--primary) !important;
            font-weight: 700;
        }
        .flatpickr-day.inRange {
            background: var(--primary-fade) !important;
            box-shadow: -5px 0 0 var(--primary-fade), 5px 0 0 var(--primary-fade) !important;
            border-color: transparent !important;
        }
        .flatpickr-day.disabled {
            color: #d1d5db !important;
            cursor: not-allowed !important;
            background-color: #f3f4f6 !important;
        }


        /* --- Inputs Styling --- */
        .input-row { display: flex; gap: 15px; margin-bottom: 20px; }
        .input-group { flex: 1; }
        .input-group label {
            display: block; font-size: 0.7rem; font-weight: 800; color: var(--gray);
            margin-bottom: 6px; text-transform: uppercase;
        }
        .input-group input {
            width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0;
            border-radius: 10px; font-size: 1rem; font-weight: 700;
            font-family: 'Inter', sans-serif; transition: all 0.2s; box-sizing: border-box;
        }
        .input-group input:focus { border-color: var(--dark); outline: none; }

        /* ========================================= */
        /* === NOUVEAU STYLE DU TICKET DE CAISSE === */
        /* ========================================= */
        .receipt-container {
            background-color: #F5F7F9; 
            border: 2px solid #E1E4E8;
            border-radius: 16px;
            padding: 20px;
            margin-top: auto; 
        }

        .receipt-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--gray);
            border-bottom: 1px solid #EBEBEB;
        }
        
        .receipt-line:last-of-type { border-bottom: none; padding-bottom: 0; }

        .receipt-line.promo { color: var(--success); }
        .receipt-line span.value { font-weight: 700; color: var(--dark); font-size: 1.05rem;}
        .receipt-line.promo span.value { color: var(--success); }

        .receipt-divider-strong {
            height: 3px; 
            background-color: var(--dark); 
            margin: 15px 0 20px 0;
            border-radius: 2px;
        }

        .receipt-total-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--primary-super-fade);
            padding: 15px;
            border-radius: 12px;
            border: 1px solid rgba(255, 56, 92, 0.2);
        }
        .receipt-total-box .label {
            font-size: 1.1rem; font-weight: 800; text-transform: uppercase;
        }
        .receipt-total-box .amount {
            font-size: 1.6rem; font-weight: 900; color: var(--primary); line-height: 1;
        }

        /* Input caché pour Flatpickr */
        #date-range { display: none; }

        /* Responsive */
        @media (max-width: 767px) {
            .input-row { flex-direction: column; gap: 15px; }
            .controls-section { border-left: none; border-top: 2px solid #f0f0f0; }
            .calendar-section { padding: 15px 10px; }
            .receipt-total-box .amount { font-size: 1.4rem; }
        }
    </style>
</head>
<body>

    <div class="container">
        <header>
            <h1>🗓️ Simulateur de Séjour</h1>
            <div id="sync-status" class="status-badge <?php echo $sync_status; ?>">
                <?php echo ($sync_status === 'error' ? '⚠️ ' : '✅ ') . $sync_message; ?>
            </div>
        </header>

        <div class="content-grid">
            
            <div class="calendar-section">
                <input type="text" id="date-range" placeholder="Sélectionner dates">
            </div>

            <div class="controls-section">
                
                <div class="input-row">
                    <div class="input-group">
                        <label>Tarif Nuit (€)</label>
                        <input type="number" id="daily-rate" value="90">
                    </div>
                    <div class="input-group">
                        <label>Caution (€)</label>
                        <input type="number" id="caution-amount" value="0">
                    </div>
                </div>

                <div class="input-group" style="margin-bottom: 25px;">
                    <label>Code Promo / Remise (%)</label>
                    <input type="number" id="discount-percent" value="0" min="0" max="100" placeholder="ex: 10">
                </div>

                <div class="receipt-container">
                    <div class="receipt-line">
                        <span id="txt-base">Prix du séjour</span>
                        <span class="value" id="val-base">0.00 €</span>
                    </div>

                    <div class="receipt-line promo" id="row-promo" style="display: none;">
                        <span>🎁 Remise appliquée (<span id="txt-promo-percent">0</span>%)</span>
                        <span class="value" id="val-promo">- 0.00 €</span>
                    </div>

                    <div class="receipt-line">
                        <span>Caution (remboursable)</span>
                        <span class="value" id="val-caution">500.00 €</span>
                    </div>

                    <div class="receipt-divider-strong"></div>

                    <div class="receipt-total-box">
                        <span class="label">Total à régler</span> <br>
                        <span class="amount" id="val-total">0.00 €</span>
                    </div>
                </div>
                </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/fr.js"></script>
    <script>
        let fpInstance = null;
        let startDate = null;
        let endDate = null;
        // Les dates bloquées sont passées directement par PHP
        // Le format est un tableau d'objets : [{from: 'YYYY-MM-DD', to: 'YYYY-MM-DD'}, ...]
        const reservedDates = <?php echo $json_reserved_ranges; ?>;

        function initCalendar() {
            const isMobile = window.innerWidth < 768;

            fpInstance = flatpickr("#date-range", {
                mode: "range", 
                inline: true, 
                locale: "fr", 
                dateFormat: "Y-m-d",
                minDate: "today", 
                // Utilisation des plages de dates réservées venant du PHP
                disable: reservedDates, 
                animate: true,
                showMonths: isMobile ? 1 : 2,
                onChange: function(selectedDates) {
                    if (selectedDates.length === 2) {
                        startDate = selectedDates[0]; endDate = selectedDates[1];
                        if (startDate > endDate) [startDate, endDate] = [endDate, startDate];
                    } else {
                        startDate = null; endDate = null;
                    }
                    calculateReceipt();
                }
            });
        }

        function calculateReceipt() {
            const rate = parseFloat(document.getElementById('daily-rate').value) || 0;
            const caution = parseFloat(document.getElementById('caution-amount').value) || 0;
            const discountPct = parseFloat(document.getElementById('discount-percent').value) || 0;

            const elTxtBase = document.getElementById('txt-base');
            const elValBase = document.getElementById('val-base');
            const elRowPromo = document.getElementById('row-promo');
            const elTxtPromoPct = document.getElementById('txt-promo-percent');
            const elValPromo = document.getElementById('val-promo');
            const elValCaution = document.getElementById('val-caution');
            const elValTotal = document.getElementById('val-total');

            elValCaution.textContent = caution.toFixed(2) + ' €';

            if (!startDate || !endDate) {
                elTxtBase.textContent = "Prix du séjour (0 nuits)";
                elValBase.textContent = "0.00 €";
                elRowPromo.style.display = "none";
                // Total à régler quand rien n'est sélectionné est juste la caution
                elValTotal.textContent = caution.toFixed(2) + " €"; 
                return;
            }

            const oneDay = 1000 * 60 * 60 * 24;
            const nights = Math.round((endDate.getTime() - startDate.getTime()) / oneDay);

            const baseCost = nights * rate;
            let discountAmount = 0;

            if (discountPct > 0) {
                discountAmount = baseCost * (discountPct / 100);
                elRowPromo.style.display = "flex";
                elTxtPromoPct.textContent = discountPct;
                elValPromo.textContent = "- " + discountAmount.toFixed(2) + " €";
            } else {
                elRowPromo.style.display = "none";
            }

            const finalTotal = baseCost - discountAmount + caution;

            elTxtBase.textContent = `Prix du séjour (${nights} nuit${nights > 1 ? 's' : ''})`;
            elValBase.textContent = baseCost.toFixed(2) + " €";
            elValTotal.textContent = finalTotal.toFixed(2) + " €";
        }

        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                const isMobile = window.innerWidth < 768;
                if (fpInstance && fpInstance.config.showMonths !== (isMobile ? 1 : 2)) {
                    fpInstance.destroy();
                    initCalendar();
                    if(startDate && endDate) fpInstance.setDate([startDate, endDate]);
                }
            }, 200);
        });

        document.addEventListener('DOMContentLoaded', () => {
            initCalendar();
            document.querySelectorAll('input[type="number"]').forEach(input => {
                input.addEventListener('input', calculateReceipt);
            });
            calculateReceipt();
        });
    </script>
</body>
</html>