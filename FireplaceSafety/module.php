<?php

declare(strict_types=1);

class FireplaceSafety extends IPSModuleStrict
{
    public function Create(): void{
        parent::Create();

        // --- Konfiguration (Properties) ---
        // Sensoren
        $this->RegisterPropertyInteger("SensorOvenTemp", 0);
        $this->RegisterPropertyInteger("SensorRoomTemp", 0);
        $this->RegisterPropertyInteger("SensorOvenDoor", 0);
        $this->RegisterPropertyString("OvenDoorClosedValue", "false");
        $this->RegisterPropertyString("SensorWindows", "[]");
        // Aktoren
        $this->RegisterPropertyInteger("ActuatorHood", 0);
        // Parameter
        $this->RegisterPropertyFloat("OvenDeltaTemp", 15.0);
        $this->RegisterPropertyFloat("PeakDropThreshold", 5.0);
        $this->RegisterPropertyFloat("MaxRoomTemp", 24.0);
        $this->RegisterPropertyInteger("DoorAlarmTime", 300);

        // --- Status-Variablen ---
        $this->RegisterVariableFloat("CurrentDeltaTemp", "Aktuelle Temperatur-Differenz", "");
        $this->RegisterVariableBoolean("CurrentDoorStatus", "Status Ofentür", "");
        $this->RegisterVariableBoolean("OvenStatus", "Status Kaminofen", "");
        $this->RegisterVariableBoolean("HoodStatus", "Status Dunstabzugshaube", "");
        $this->RegisterVariableBoolean("AlarmOvenDoor", "Alarm Ofentür", "");
        $this->EnableAction("AlarmOvenDoor"); // Quittierbar per Webfront

        $this->RegisterVariableFloat("OvenPeakTemp", "Letzte Spitzen-Temperatur", "");
        $this->RegisterVariableBoolean("WoodRefillNeeded", "Bitte Holz nachlegen", "");

        // --- Timers ---
        $this->RegisterTimer("DoorAlarmTimer", 0, 'FS_TriggerDoorAlarm($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void{
        parent::ApplyChanges();
        
        // --- Auto-generated References ---
        foreach ($this->GetReferenceList() as $refID) {
            $this->UnregisterReference($refID);
        }
        $ref_SensorOvenTemp = $this->ReadPropertyInteger('SensorOvenTemp');
        if ($ref_SensorOvenTemp > 1 && @IPS_ObjectExists($ref_SensorOvenTemp)) {
            $this->RegisterReference($ref_SensorOvenTemp);
        }
        $ref_SensorRoomTemp = $this->ReadPropertyInteger('SensorRoomTemp');
        if ($ref_SensorRoomTemp > 1 && @IPS_ObjectExists($ref_SensorRoomTemp)) {
            $this->RegisterReference($ref_SensorRoomTemp);
        }
        $ref_SensorOvenDoor = $this->ReadPropertyInteger('SensorOvenDoor');
        if ($ref_SensorOvenDoor > 1 && @IPS_ObjectExists($ref_SensorOvenDoor)) {
            $this->RegisterReference($ref_SensorOvenDoor);
        }
        $ref_ActuatorHood = $this->ReadPropertyInteger('ActuatorHood');
        if ($ref_ActuatorHood > 1 && @IPS_ObjectExists($ref_ActuatorHood)) {
            $this->RegisterReference($ref_ActuatorHood);
        }
        $list_SensorWindows = json_decode($this->ReadPropertyString('SensorWindows'), true);
        if (is_array($list_SensorWindows)) {
            foreach ($list_SensorWindows as $item) {
                $vid = $item['VariableID'] ?? 0;
                if ($vid > 1 && @IPS_ObjectExists($vid)) {
                    $this->RegisterReference($vid);
                }
            }
        }
        // ---------------------------------

        // Symcon 8 Custom Presentations
        if (function_exists('IPS_SetVariableCustomPresentation')) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('CurrentDeltaTemp'), ['ICON' => 'Temperature', 'SUFFIX' => ' °C']);
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('CurrentDoorStatus'), ['ICON' => 'Window']);
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('AlarmOvenDoor'), ['ICON' => 'Warning']);
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('OvenPeakTemp'), ['ICON' => 'Temperature', 'SUFFIX' => ' °C']);
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('WoodRefillNeeded'), ['ICON' => 'Warning']);
            
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('OvenStatus'), ['ICON' => 'Flame']);
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('HoodStatus'), ['ICON' => 'Information']);
        }

        // --- Custom Profiles ---
        if (!IPS_VariableProfileExists('SmartClimate.OvenStatus')) {
            IPS_CreateVariableProfile('SmartClimate.OvenStatus', 0); // Boolean
            IPS_SetVariableProfileAssociation('SmartClimate.OvenStatus', false, 'Aus', 'Flame', -1);
            IPS_SetVariableProfileAssociation('SmartClimate.OvenStatus', true, 'Brennt', 'Flame', 0xFF0000);
        }
        IPS_SetVariableCustomProfile($this->GetIDForIdent('OvenStatus'), 'SmartClimate.OvenStatus');

        if (!IPS_VariableProfileExists('SmartClimate.HoodStatus')) {
            IPS_CreateVariableProfile('SmartClimate.HoodStatus', 0); // Boolean
            IPS_SetVariableProfileAssociation('SmartClimate.HoodStatus', false, 'Gesperrt', 'Lock', 0xFF0000);
            IPS_SetVariableProfileAssociation('SmartClimate.HoodStatus', true, 'Freigegeben', 'Unlock', 0x00FF00);
        }
        IPS_SetVariableCustomProfile($this->GetIDForIdent('HoodStatus'), 'SmartClimate.HoodStatus');


        // Clear all previous message registrations
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        // Register Sensor Messages
        $ovenTemp = $this->ReadPropertyInteger("SensorOvenTemp");
        if ($ovenTemp > 0 && IPS_VariableExists($ovenTemp)) {
            $this->RegisterMessage($ovenTemp, VM_UPDATE);
        }

        $roomTemp = $this->ReadPropertyInteger("SensorRoomTemp");
        if ($roomTemp > 0 && IPS_VariableExists($roomTemp)) {
            $this->RegisterMessage($roomTemp, VM_UPDATE);
        }

        $ovenDoor = $this->ReadPropertyInteger("SensorOvenDoor");
        if ($ovenDoor > 0 && IPS_VariableExists($ovenDoor)) {
            $this->RegisterMessage($ovenDoor, VM_UPDATE);
        }

        $windows = json_decode($this->ReadPropertyString("SensorWindows"), true) ?: [];
        foreach ($windows as $win) {
            $vid = $win['VariableID'] ?? 0;
            if ($vid > 0 && IPS_VariableExists($vid)) {
                $this->RegisterMessage($vid, VM_UPDATE);
            }
        }

        // Initial update
        $this->UpdateSafety();
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void{
        $this->UpdateSafety();
    }

    public function RequestAction(string $Ident, $Value): void{
        switch ($Ident) {
            case "AlarmOvenDoor":
                // Quittierung des Ofentür-Alarms
                if ($Value == false) {
                    $this->SetValue($Ident, false);
                    $this->UpdateSafety();
                }
                break;
            default:
                throw new Exception("Invalid Ident");
        }
    }

    public function TriggerDoorAlarm()
    {
        // Wird aufgerufen, wenn der Timer abläuft
        $this->SetTimerInterval("DoorAlarmTimer", 0);
        $this->SetValueIfChanged("AlarmOvenDoor", true);
        $this->SendDebug("Timer", "Ofentür-Alarm ausgelöst!", 0);
    }

    private function SetValueIfChanged(string $Ident, $Value)
    {
        if ($this->GetValue($Ident) !== $Value) {
            $this->SetValue($Ident, $Value);
        }
    }

    private function UpdateSafety()
    {
        $ovenTempId = $this->ReadPropertyInteger("SensorOvenTemp");
        $roomTempId = $this->ReadPropertyInteger("SensorRoomTemp");
        
        $isOvenOn = false;
        
        // --- 1. Temperatur- & Peak-Logik auswerten ---
        if ($ovenTempId > 0 && IPS_VariableExists($ovenTempId) && $roomTempId > 0 && IPS_VariableExists($roomTempId)) {
            $tOven = GetValue($ovenTempId);
            $tRoom = GetValue($roomTempId);
            $deltaSetting = $this->ReadPropertyFloat("OvenDeltaTemp");
            
            $currentDelta = $tOven - $tRoom;
            $this->SetValueIfChanged("CurrentDeltaTemp", $currentDelta);
            
            if ($currentDelta >= $deltaSetting) {
                $isOvenOn = true;
            }

            // Peak tracking und "Nachlegen"-Logik
            $refillNeeded = false;
            if ($isOvenOn) {
                $peak = $this->GetValue("OvenPeakTemp");
                if ($tOven > $peak) {
                    // Neuer Peak erreicht
                    $peak = $tOven;
                    $this->SetValue("OvenPeakTemp", $peak);
                }
                
                // Wenn Temperatur vom Peak um Threshold abfällt, ist es Zeit nachzulegen...
                if ($peak > 0 && $tOven <= ($peak - $this->ReadPropertyFloat("PeakDropThreshold"))) {
                    // ...außer der Raum ist ohnehin schon wärmer als MaxRoomTemp
                    if ($tRoom < $this->ReadPropertyFloat("MaxRoomTemp")) {
                        $refillNeeded = true;
                    }
                }
            } else {
                $this->SetValueIfChanged("OvenPeakTemp", 0.0);
            }
            $this->SetValueIfChanged("WoodRefillNeeded", $refillNeeded);
        } else {
            // Sensoren fehlen oder ungültig
            $this->SetValueIfChanged("OvenPeakTemp", 0.0);
            $this->SetValueIfChanged("WoodRefillNeeded", false);
        }
        $this->SetValueIfChanged("OvenStatus", $isOvenOn);

        // --- 2. Fenster-Sensoren auswerten ---
        $anyWindowOpen = false;
        $windows = json_decode($this->ReadPropertyString("SensorWindows"), true) ?: [];
        foreach ($windows as $win) {
            $vid = $win['VariableID'] ?? 0;
            $closedValStr = $win['ClosedValue'] ?? "false";
            
            if ($vid > 0 && IPS_VariableExists($vid)) {
                $currentVal = GetValue($vid);
                if (!$this->IsTriggered($currentVal, $closedValStr)) {
                    $anyWindowOpen = true;
                    break;
                }
            }
        }

        // --- 3. Ofentür auswerten ---
        $isDoorOpen = false;
        $ovenDoorId = $this->ReadPropertyInteger("SensorOvenDoor");
        if ($ovenDoorId > 0 && IPS_VariableExists($ovenDoorId)) {
            $doorVal = GetValue($ovenDoorId);
            $doorClosedValStr = $this->ReadPropertyString("OvenDoorClosedValue");
            if (!$this->IsTriggered($doorVal, $doorClosedValStr)) {
                $isDoorOpen = true;
            }
        }
        $this->SetValueIfChanged("CurrentDoorStatus", $isDoorOpen);

        // Tür-Alarm Logik (Startet, wenn Tür auf und Ofen brennt)
        if ($isOvenOn && $isDoorOpen) {
            if ($this->GetTimerInterval("DoorAlarmTimer") == 0 && !$this->GetValue("AlarmOvenDoor")) {
                $delay = $this->ReadPropertyInteger("DoorAlarmTime");
                $this->SetTimerInterval("DoorAlarmTimer", $delay * 1000);
                $this->SendDebug("Timer", "Ofentür geöffnet, Timer gestartet ($delay Sekunden)", 0);
            }
        } else {
            if ($this->GetTimerInterval("DoorAlarmTimer") > 0) {
                $this->SetTimerInterval("DoorAlarmTimer", 0);
                $this->SendDebug("Timer", "Ofentür geschlossen oder Ofen aus, Timer gestoppt", 0);
            }
            if ($this->GetValue("AlarmOvenDoor")) {
                $this->SetValue("AlarmOvenDoor", false);
            }
        }

        // --- 4. Dunstabzugshauben Sicherheits-Logik ---
        // Haube darf nur an sein, wenn der Ofen aus ist ODER ein Fenster geöffnet ist.
        $allowHood = true;
        if ($isOvenOn && !$anyWindowOpen) {
            $allowHood = false;
        }
        $this->SetValue("HoodStatus", $allowHood);

        $actuatorId = $this->ReadPropertyInteger("ActuatorHood");
        if ($actuatorId > 0 && IPS_VariableExists($actuatorId)) {
            $currentPlug = GetValue($actuatorId);
            
            // Cast auf bool, um sicheren Vergleich zu haben
            $currentPlugBool = (bool)$currentPlug;
            
            if ($currentPlugBool !== $allowHood) {
                $this->SendDebug("Actuator", "Schalte Dunstabzugshaube: ". ($allowHood ? "AN": "AUS"), 0);
                try {
                    RequestAction($actuatorId, $allowHood);
                } catch (\Throwable $e) {
                    $this->LogMessage("Fehler beim Schalten der Dunstabzugshaube (ID $actuatorId): ". $e->getMessage(), KL_ERROR);
                }
            }
        }
    }

    private function IsTriggered($currentVal, $triggerValStr)
    {
        if (is_bool($currentVal)) {
            $t = strtolower(trim($triggerValStr));
            $triggerBool = ($t === 'true'|| $t === '1');
            return $currentVal === $triggerBool;
        }

        if (is_int($currentVal) || is_float($currentVal)) {
            return $currentVal == (float)$triggerValStr;
        }

        return (string)$currentVal === $triggerValStr;
    }

    protected function LogMessage(string $Message, int $Type): bool
    {
        IPS_LogMessage('SmartVillaKunterbunt', 'FireplaceSafety: '. $Message);
        return true;
    }

    public function GetConfigurationForm(): string
    {
        return <<<'EOT'
{
    "elements": [
        {
            "type": "ExpansionPanel",
            "caption": "⚙ Sensoren (Eingänge)",
            "items": [
                {
                    "type": "RowLayout",
                    "items": [
                        {
                            "type": "SelectVariable",
                            "name": "SensorOvenTemp",
                            "caption": "Temperatur Ofen / Abgasrohr"
                        },
                        {
                            "type": "SelectVariable",
                            "name": "SensorRoomTemp",
                            "caption": "Temperatur Raum"
                        }
                    ]
                },
                {
                    "type": "RowLayout",
                    "items": [
                        {
                            "type": "SelectVariable",
                            "name": "SensorOvenDoor",
                            "caption": "Ofentür-Kontakt"
                        },
                        {
                            "type": "ValidationTextBox",
                            "name": "OvenDoorClosedValue",
                            "caption": "Ofentür-Kontakt: Wert für 'Geschlossen'(z.B. false, 0, geschlossen)"
                        }
                    ]
                }
            ]
        },
        {
            "type": "List",
            "name": "SensorWindows",
            "caption": "Fenster-Kontakte (Zuluft)",
            "add": true,
            "delete": true,
            "columns": [
                {
                    "caption": "Fenster Sensor",
                    "name": "VariableID",
                    "width": "auto",
                    "add": 0,
                    "edit": {
                        "type": "SelectVariable"
                    }
                },
                {
                    "caption": "Wert für Geschlossen",
                    "name": "ClosedValue",
                    "width": "150px",
                    "add": "false",
                    "edit": {
                        "type": "ValidationTextBox"
                    }
                }
            ]
        },
        {
            "type": "ExpansionPanel",
            "caption": "🔧 Parameter & Schwellenwerte",
            "items": [
                {
                    "type": "Label",
                    "caption": "Hier stellst du ein, wie sensibel das Modul auf Temperaturveränderungen reagieren soll:"
                },
                {
                    "type": "RowLayout",
                    "items": [
                        {
                            "type": "NumberSpinner",
                            "name": "OvenDeltaTemp",
                            "caption": "Ofen an ab Temp-Delta (°C)",
                            "digits": 1,
                            "minimum": 1,
                            "maximum": 50
                        },
                        {
                            "type": "NumberSpinner",
                            "name": "PeakDropThreshold",
                            "caption": "Temp-Abfall für 'Nachlegen' (°C)",
                            "digits": 1,
                            "minimum": 1,
                            "maximum": 50
                        }
                    ]
                },
                {
                    "type": "Label",
                    "caption": "Erklärung: \n- 'Ofen an': Der Kamin gilt als eingeschaltet, wenn der Ofenfühler um diesen Wert wärmer ist als der Raum.\n- 'Nachlegen': Fällt die Temperatur nach dem Höhepunkt um diesen Wert ab, wird zum Nachlegen geraten."
                },
                {
                    "type": "RowLayout",
                    "items": [
                        {
                            "type": "NumberSpinner",
                            "name": "MaxRoomTemp",
                            "caption": "Max. Raumtemp. für 'Nachlegen' (°C)",
                            "digits": 1,
                            "minimum": 10,
                            "maximum": 35
                        },
                        {
                            "type": "NumberSpinner",
                            "name": "DoorAlarmTime",
                            "caption": "Ofentür-Alarm Vorwarnzeit (s)",
                            "minimum": 0,
                            "maximum": 3600
                        }
                    ]
                },
                {
                    "type": "Label",
                    "caption": "Erklärung: \n- 'Max. Raumtemp': Ist der Raum bereits wärmer als dieser Wert, blockiert das Modul die Nachlege-Meldung.\n- 'Vorwarnzeit': Wie lange darf die Ofentür bei brennendem Ofen offen stehen, bevor ein Alarm ausgelöst wird?"
                }
            ]
        },
        {
            "type": "Label",
            "caption": "Aktoren (Ausgänge)\nHier wählst du den Aktor für die Dunstabzugshaube aus:"
        },
        {
            "type": "SelectVariable",
            "name": "ActuatorHood",
            "caption": "Schaltsteckdose Dunstabzugshaube"
        }
    ]
}
EOT;
    }
}
