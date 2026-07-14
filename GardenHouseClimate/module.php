<?php

declare(strict_types=1);

class GardenHouseClimate extends IPSModuleStrict
{
    public function Create(): void{
        parent::Create();

        // Properties
        $this->RegisterPropertyInteger("SensorTempInside", 0);
        $this->RegisterPropertyInteger("SensorTempOutside", 0);
        $this->RegisterPropertyString("SensorWindows", "[]");
        
        $this->RegisterPropertyInteger("ActuatorHeaterPlug", 0);
        $this->RegisterPropertyInteger("SensorHeaterPower", 0);
        
        $this->RegisterPropertyFloat("TargetTemperature", 5.0);
        $this->RegisterPropertyFloat("Hysteresis", 0.5);
        
        $this->RegisterPropertyFloat("HeaterPowerThreshold", 50.0);
        $this->RegisterPropertyInteger("HeaterDefectTime", 300);
        $this->RegisterPropertyInteger("WindowOpenTime", 900);
        $this->RegisterPropertyFloat("FrostWarningTemp", 3.0);
        
        // Variables
        $this->RegisterVariableBoolean("WinterMode", "Winterbetrieb", "~Switch");
        $this->EnableAction("WinterMode");
        $this->SetValue("WinterMode", true); // Default to true
        
        $this->RegisterVariableFloat("TargetTemperature", "Zieltemperatur Frostschutz", "~Temperature");
        $this->EnableAction("TargetTemperature");
        
        
        $this->RegisterVariableInteger("HeaterStatus", "Status Heizung", "GHC.HeaterStatus");
        
        if (!IPS_VariableProfileExists('SmartClimate.HeaterStatus')) {
            IPS_CreateVariableProfile('SmartClimate.HeaterStatus', 1);
            IPS_SetVariableProfileAssociation('SmartClimate.HeaterStatus', 0, 'Aus', 'Sleep', 0x00FF00);
            IPS_SetVariableProfileAssociation('SmartClimate.HeaterStatus', 1, 'Heizen', 'Flame', 0xFF0000);
            IPS_SetVariableProfileAssociation('SmartClimate.HeaterStatus', 2, 'Pausiert (Fenster offen)', 'Window', 0xFFFF00);
        }
        IPS_SetVariableCustomProfile($this->GetIDForIdent('HeaterStatus'), 'SmartClimate.HeaterStatus');
        
        // Alarms (Require Acknowledge)
        $this->RegisterVariableBoolean("AlarmHeaterDefect", "Alarm: Heizung defekt", "~Alert");
        $this->EnableAction("AlarmHeaterDefect");
        
        $this->RegisterVariableBoolean("AlarmFrost", "Alarm: Kritischer Frost", "~Alert");
        $this->EnableAction("AlarmFrost");
        
        $this->RegisterVariableBoolean("AlarmWindowOpen", "Alarm: Fenster offen (Winter)", "~Alert");
        $this->EnableAction("AlarmWindowOpen");
        
        // Timers
        $this->RegisterTimer("HeaterDefectTimer", 0, 'GHC_TriggerHeaterDefectAlarm($_IPS[\'TARGET\']);');
        $this->RegisterTimer("WindowOpenTimer", 0, 'GHC_TriggerWindowOpenAlarm($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void{
        parent::ApplyChanges();
        // --- Auto-generated References ---
        $ref_SensorTempInside = $this->ReadPropertyInteger('SensorTempInside');
        if ($ref_SensorTempInside > 1 && @IPS_ObjectExists($ref_SensorTempInside)) {
            $this->RegisterReference($ref_SensorTempInside);
        }
        $ref_SensorTempOutside = $this->ReadPropertyInteger('SensorTempOutside');
        if ($ref_SensorTempOutside > 1 && @IPS_ObjectExists($ref_SensorTempOutside)) {
            $this->RegisterReference($ref_SensorTempOutside);
        }
        $ref_ActuatorHeaterPlug = $this->ReadPropertyInteger('ActuatorHeaterPlug');
        if ($ref_ActuatorHeaterPlug > 1 && @IPS_ObjectExists($ref_ActuatorHeaterPlug)) {
            $this->RegisterReference($ref_ActuatorHeaterPlug);
        }
        $ref_SensorHeaterPower = $this->ReadPropertyInteger('SensorHeaterPower');
        if ($ref_SensorHeaterPower > 1 && @IPS_ObjectExists($ref_SensorHeaterPower)) {
            $this->RegisterReference($ref_SensorHeaterPower);
        }
        $ref_WindowOpenTime = $this->ReadPropertyInteger('WindowOpenTime');
        if ($ref_WindowOpenTime > 1 && @IPS_ObjectExists($ref_WindowOpenTime)) {
            $this->RegisterReference($ref_WindowOpenTime);
        }
        // ---------------------------------


        
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }
        
        $sensors = [
            "SensorTempInside", "SensorTempOutside"
        ];
        
        foreach ($sensors as $sensorName) {
            $id = $this->ReadPropertyInteger($sensorName);
            if ($id > 0 && IPS_VariableExists($id)) {
                $this->RegisterMessage($id, VM_UPDATE);
            }
        }
        
        $windows = json_decode($this->ReadPropertyString("SensorWindows"), true);
        if (is_array($windows)) {
            foreach ($windows as $w) {
                $vid = $w['VariableID'] ?? 0;
                if ($vid > 0 && IPS_VariableExists($vid)) {
                    $this->RegisterMessage($vid, VM_UPDATE);
                }
            }
        }
        
        $powerId = $this->ReadPropertyInteger("SensorHeaterPower");
        if ($powerId > 0 && IPS_VariableExists($powerId)) {
            $this->RegisterMessage($powerId, VM_UPDATE);
        }
        
        $this->UpdateClimate();
    }
    
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void{
        $powerId = $this->ReadPropertyInteger("SensorHeaterPower");
        $isWindow = false;
        
        $windows = json_decode($this->ReadPropertyString("SensorWindows"), true);
        if (is_array($windows)) {
            foreach ($windows as $w) {
                if (($w['VariableID'] ?? 0) == $SenderID) {
                    $isWindow = true;
                    break;
                }
            }
        }
        
        if ($SenderID == $powerId) {
            $this->HandlePowerUpdate($Data[0]);
        } elseif ($isWindow) {
            // Check if any window is open
            $windowOpen = false;
            if (is_array($windows)) {
                foreach ($windows as $w) {
                    $vid = $w['VariableID'] ?? 0;
                    $closedVal = $w['ClosedValue'] ?? 'false';
                    if ($vid > 0 && IPS_VariableExists($vid)) {
                        if ($this->IsWindowOpen($vid, $closedVal)) {
                            $windowOpen = true;
                            break;
                        }
                    }
                }
            }
            $this->HandleWindowUpdate($windowOpen);
            $this->UpdateClimate();
        } else {
            $this->UpdateClimate();
        }
    }
    
    public function RequestAction(string $Ident, $Value): void{
        switch ($Ident) {
            case "TargetTemperature":
                $this->SetValue($Ident, $Value);
                $this->UpdateClimate();
                break;
            case "WinterMode":
                $this->SetValue("WinterMode", $Value);
                $this->UpdateClimate();
                break;
            case "AlarmHeaterDefect":
            case "AlarmFrost":
            case "AlarmWindowOpen":
                if ($Value == false) {
                    $this->SetValue($Ident, false);
                    $this->UpdateClimate();
                }
                break;
            default:
                throw new Exception("Invalid Ident");
        }
    }

    public function UpdateClimate()
    {
        $winterMode = $this->GetValue("WinterMode");
        if (!$winterMode) {
            $this->SetHeater(false, 0); // Off (Summer)
            $this->SetTimerInterval("HeaterDefectTimer", 0);
            $this->SetTimerInterval("WindowOpenTimer", 0);
            return;
        }
        
        $tempIn = $this->GetVarValue("SensorTempInside");
        $tempOut = $this->GetVarValue("SensorTempOutside");
        
        $windowOpen = false;
        $windows = json_decode($this->ReadPropertyString("SensorWindows"), true);
        if (is_array($windows)) {
            foreach ($windows as $w) {
                $vid = $w['VariableID'] ?? 0;
                $closedVal = $w['ClosedValue'] ?? 'false';
                if ($vid > 0 && IPS_VariableExists($vid)) {
                    if ($this->IsWindowOpen($vid, $closedVal)) {
                        $windowOpen = true;
                        break;
                    }
                }
            }
        }
        
        if ($tempIn === null) return;
        
        // Check Frost Alarm
        $frostWarn = $this->ReadPropertyFloat("FrostWarningTemp");
        if ($tempIn <= $frostWarn) {
            $this->SetValue("AlarmFrost", true);
        }
        
        // Window check: if open, turn off heater to save energy
        if ($windowOpen) {
            $this->SetHeater(false, 2); // Off (Window Open)
            return;
        }
        
        // Target Temp & Vorsteuerung
        $targetTemp = $this->GetValue("TargetTemperature");
        if ($tempOut !== null && $tempOut <= -5.0) {
            $targetTemp += 1.0; // Puffer bei starkem Frost
        }
        
        $hysteresis = $this->ReadPropertyFloat("Hysteresis");
        
        $plugId = $this->ReadPropertyInteger("ActuatorHeaterPlug");
        if ($plugId == 0 || !IPS_VariableExists($plugId)) return;
        
        $plugStatus = GetValue($plugId);
        
        if ($tempIn < ($targetTemp - ($hysteresis / 2))) {
            $this->SetHeater(true, 1);
        } elseif ($tempIn > ($targetTemp + ($hysteresis / 2))) {
            $this->SetHeater(false, 0);
        } else {
            // Keep current status
            $this->SetValue("HeaterStatus", $plugStatus ? 1 : 0);
        }
    }
    
    private function SetHeater($state, $statusText)
    {
        $plugId = $this->ReadPropertyInteger("ActuatorHeaterPlug");
        if ($plugId == 0 || !IPS_VariableExists($plugId)) return;
        
        $plugStatus = GetValue($plugId);
        if ($plugStatus != $state) {
            RequestAction($plugId, $state);
        }
        $this->SetValue("HeaterStatus", $statusText);
        
        // If we turned it off, stop defect timer
        if (!$state) {
            $this->SetTimerInterval("HeaterDefectTimer", 0);
        }
    }
    
    private function HandlePowerUpdate($currentPower)
    {
        $winterMode = $this->GetValue("WinterMode");
        if (!$winterMode) return;
        
        $plugId = $this->ReadPropertyInteger("ActuatorHeaterPlug");
        if ($plugId == 0) return;
        
        $plugStatus = GetValue($plugId);
        $threshold = $this->ReadPropertyFloat("HeaterPowerThreshold");
        $timeLimit = $this->ReadPropertyInteger("HeaterDefectTime");
        
        if ($plugStatus) {
            if ($currentPower < $threshold) {
                // Heater is logically ON but draws no power -> Defect?
                $timerData = $this->GetTimerInterval("HeaterDefectTimer");
                if ($timerData == 0) {
                    $this->SetTimerInterval("HeaterDefectTimer", $timeLimit * 1000);
                }
            } else {
                $this->SetTimerInterval("HeaterDefectTimer", 0);
            }
        } else {
            $this->SetTimerInterval("HeaterDefectTimer", 0);
        }
    }
    
    public function TriggerHeaterDefectAlarm()
    {
        $this->SetTimerInterval("HeaterDefectTimer", 0);
        $this->SetValue("AlarmHeaterDefect", true);
    }
    
    private function HandleWindowUpdate($isOpen)
    {
        $winterMode = $this->GetValue("WinterMode");
        if (!$winterMode) return;
        
        $timeLimit = $this->ReadPropertyInteger("WindowOpenTime");
        
        if ($isOpen) {
            $this->SetTimerInterval("WindowOpenTimer", $timeLimit * 1000);
        } else {
            $this->SetTimerInterval("WindowOpenTimer", 0);
        }
    }
    
    public function TriggerWindowOpenAlarm()
    {
        $this->SetTimerInterval("WindowOpenTimer", 0);
        $this->SetValue("AlarmWindowOpen", true);
    }
    
    private function GetVarValue($propertyName)
    {
        $id = $this->ReadPropertyInteger($propertyName);
        if ($id > 0 && IPS_VariableExists($id)) {
            return GetValue($id);
        }
        return null;
    }
    
    private function IsWindowOpen($vid, $closedValue)
    {
        $currentVal = GetValue($vid);
        $isClosed = false;
        
        if (is_bool($currentVal)) {
            $targetBool = ($closedValue === 'true'|| $closedValue === '1'|| strtolower((string)$closedValue) === 'wahr');
            $isClosed = ($currentVal === $targetBool);
        } else {
            $isClosed = ((string)$currentVal === (string)$closedValue);
        }
        
        return !$isClosed;
    }

    protected function LogMessage(string $Message, int $Type): bool
    {
        IPS_LogMessage('SmartVillaKunterbunt', 'GardenHouseClimate: '. $Message);
        return true;
    }

    public function GetConfigurationForm(): string
    {
        return <<<'EOT'
{
    "elements": [
        {
            "type": "ExpansionPanel",
            "caption": "⚙ Sensoren",
            "items": [
                {
                    "type": "RowLayout",
                    "items": [
                        {
                            "type": "SelectVariable",
                            "name": "SensorTempInside",
                            "caption": "Temperatur Gartenhaus"
                        },
                        {
                            "type": "SelectVariable",
                            "name": "SensorTempOutside",
                            "caption": "Temperatur Außen"
                        }
                    ]
                }
            ]
        },
        {
            "type": "List",
            "name": "SensorWindows",
            "caption": "Fenster-/Türkontakte (Gartenhaus)",
            "add": true,
            "delete": true,
            "columns": [
                {
                    "caption": "Sensor",
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
            "caption": "Aktoren"
        },
        {
            "type": "SelectVariable",
            "name": "ActuatorHeaterPlug",
            "caption": "Schaltsteckdose Heizung"
        },
        {
            "type": "SelectVariable",
            "name": "SensorHeaterPower",
            "caption": "Leistungsmessung Heizung (Watt)"
        },
        {
            "type": "Label",
            "caption": "Temperatureinstellungen"
        },
        {
            "type": "NumberSpinner",
            "name": "Hysteresis",
            "caption": "Schalthysterese (°C)",
            "digits": 1
        },
        {
            "type": "Label",
            "caption": "Ausfallsicherheit / Alarme"
        },
        {
            "type": "NumberSpinner",
            "name": "HeaterPowerThreshold",
            "caption": "Erwarteter Mindest-Verbrauch bei AN (Watt)",
            "digits": 1
        },
        {
            "type": "NumberSpinner",
            "name": "HeaterDefectTime",
            "caption": "Zeit bis Defekt-Alarm (Sekunden)"
        },
        {
            "type": "NumberSpinner",
            "name": "WindowOpenTime",
            "caption": "Zeit bis Fenster-offen-Alarm im Winter (Sekunden)"
        },
        {
            "type": "NumberSpinner",
            "name": "FrostWarningTemp",
            "caption": "Temperatur für kritischen Frost-Alarm (°C)",
            "digits": 1
        }
    ]
}
EOT;
    }
}


