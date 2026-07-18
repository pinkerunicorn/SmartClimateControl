<?php

declare(strict_types=1);

class FireplaceSafety extends IPSModuleStrict
{
    public function Create(): void{
        parent::Create();

        // Properties
        $this->RegisterPropertyInteger("SensorOvenTemp", 0);
        $this->RegisterPropertyInteger("SensorRoomTemp", 0);
        $this->RegisterPropertyInteger("SensorOvenDoor", 0);
        $this->RegisterPropertyString("OvenDoorClosedValue", "false");
        $this->RegisterPropertyString("SensorWindows", "[]");
        $this->RegisterPropertyInteger("ActuatorHood", 0);

        // Variables without legacy profiles
        $this->RegisterVariableFloat("CurrentDeltaTemp", "Aktuelle Temperatur-Differenz", "");
        
        $this->RegisterVariableBoolean("CurrentDoorStatus", "Status Ofentür", "");

        $this->RegisterVariableFloat("OvenDeltaTemp", "Temperaturdifferenz für 'Ofen AN'(°C)", "");
        $this->EnableAction("OvenDeltaTemp");
        if ($this->GetValue("OvenDeltaTemp") == 0) {
            $this->SetValue("OvenDeltaTemp", 15.0);
        }

        $this->RegisterVariableInteger("DoorAlarmTime", "Vorwarnzeit Ofentür offen", "");
        $this->EnableAction("DoorAlarmTime");
        if ($this->GetValue("DoorAlarmTime") == 0) {
            $this->SetValue("DoorAlarmTime", 300);
        }

        $this->RegisterVariableBoolean("OvenStatus", "Status Kaminofen", "");
        
        $this->RegisterVariableBoolean("HoodStatus", "Status Dunstabzugshaube", "");

        $this->RegisterVariableBoolean("AlarmOvenDoor", "Alarm Ofentür", "");
        $this->EnableAction("AlarmOvenDoor");

        $this->RegisterVariableFloat("PeakDropThreshold", "Temp-Abfall für 'Holz nachlegen' (°C)", "");
        $this->EnableAction("PeakDropThreshold");
        if ($this->GetValue("PeakDropThreshold") == 0) {
            $this->SetValue("PeakDropThreshold", 5.0);
        }

        $this->RegisterVariableFloat("MaxRoomTemp", "Max. Raumtemperatur für 'Holz nachlegen'", "");
        $this->EnableAction("MaxRoomTemp");
        if ($this->GetValue("MaxRoomTemp") == 0) {
            $this->SetValue("MaxRoomTemp", 24.0);
        }

        $this->RegisterVariableFloat("OvenPeakTemp", "Letzte Spitzen-Temperatur", "");

        $this->RegisterVariableBoolean("WoodRefillNeeded", "Bitte Holz nachlegen", "");

        // Timers
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
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('OvenDeltaTemp'), ['ICON' => 'Temperature', 'SUFFIX' => ' °C']);
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('DoorAlarmTime'), ['ICON' => 'Clock', 'SUFFIX' => ' s']);
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('AlarmOvenDoor'), ['ICON' => 'Warning']);
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('PeakDropThreshold'), ['ICON' => 'Temperature', 'SUFFIX' => ' °C']);
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('MaxRoomTemp'), ['ICON' => 'Temperature', 'SUFFIX' => ' °C']);
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('OvenPeakTemp'), ['ICON' => 'Temperature', 'SUFFIX' => ' °C']);
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('WoodRefillNeeded'), ['ICON' => 'Warning']);
            
            // Custom associations for specific booleans
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('OvenStatus'), ['ICON' => 'Flame']);
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('HoodStatus'), ['ICON' => 'Information']);
        }

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

        $this->UpdateSafety();
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void{
        $this->UpdateSafety();
    }

    public function RequestAction(string $Ident, $Value): void{
        switch ($Ident) {
            case "OvenDeltaTemp":
            case "DoorAlarmTime":
            case "PeakDropThreshold":
            case "MaxRoomTemp":
                $this->SetValue($Ident, $Value);
                $this->UpdateSafety();
                break;
            case "AlarmOvenDoor":
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
        if ($ovenTempId > 0 && IPS_VariableExists($ovenTempId) && $roomTempId > 0 && IPS_VariableExists($roomTempId)) {
            $tOven = GetValue($ovenTempId);
            $tRoom = GetValue($roomTempId);
            $deltaSetting = $this->GetValue("OvenDeltaTemp");
            
            $currentDelta = $tOven - $tRoom;
            $this->SetValueIfChanged("CurrentDeltaTemp", $currentDelta);
            
            if ($currentDelta >= $deltaSetting) {
                $isOvenOn = true;
            }

            // Peak tracking and refill logic
            $refillNeeded = false;
            if ($isOvenOn) {
                $peak = $this->GetValue("OvenPeakTemp");
                if ($tOven > $peak) {
                    $peak = $tOven;
                    $this->SetValue("OvenPeakTemp", $peak);
                }
                if ($peak > 0 && $tOven <= ($peak - $this->GetValue("PeakDropThreshold"))) {
                    if ($tRoom < $this->GetValue("MaxRoomTemp")) {
                        $refillNeeded = true;
                    }
                }
            } else {
                $this->SetValueIfChanged("OvenPeakTemp", 0.0);
            }
            $this->SetValueIfChanged("WoodRefillNeeded", $refillNeeded);
        } else {
            $this->SetValueIfChanged("OvenPeakTemp", 0.0);
            $this->SetValueIfChanged("WoodRefillNeeded", false);
        }
        $this->SetValueIfChanged("OvenStatus", $isOvenOn);

        // Check Windows
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

        // Check Door
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

        // Door Alarm Logic
        if ($isOvenOn && $isDoorOpen) {
            if ($this->GetTimerInterval("DoorAlarmTimer") == 0 && !$this->GetValue("AlarmOvenDoor")) {
                $delay = $this->GetValue("DoorAlarmTime");
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

        // Hood Logic
        // If oven is ON, we need a window open. Else, hood is safe.
        $allowHood = true;
        if ($isOvenOn && !$anyWindowOpen) {
            $allowHood = false;
        }
        $this->SetValue("HoodStatus", $allowHood);

        $actuatorId = $this->ReadPropertyInteger("ActuatorHood");
        if ($actuatorId > 0 && IPS_VariableExists($actuatorId)) {
            $currentPlug = GetValue($actuatorId);
            
            // To ensure safe types (if currentPlug is int, allowHood is bool)
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
            "type": "Label",
            "caption": "Aktoren (Ausgänge)"
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
