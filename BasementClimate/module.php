<?php

declare(strict_types=1);

class BasementClimate extends IPSModuleStrict
{
    public function Create(): void{
        parent::Create();

        // Properties
        $this->RegisterPropertyInteger("SensorTempOutside", 0);
        $this->RegisterPropertyInteger("SensorHumOutside", 0);
        $this->RegisterPropertyInteger("SensorTempInside", 0);
        $this->RegisterPropertyInteger("SensorHumInside", 0);
        $this->RegisterPropertyString("SensorWindows", "[]");
        
        $this->RegisterPropertyInteger("ActuatorDehumidifierPlug", 0);
        $this->RegisterPropertyInteger("SensorDehumidifierPower", 0);
        
        $this->RegisterPropertyInteger("ActuatorRadiator1", 0);
        $this->RegisterPropertyInteger("ActuatorRadiator2", 0);
        
        $this->RegisterPropertyFloat("DehumidifierMaxHum", 60.0);
        $this->RegisterPropertyFloat("DehumidifierMinHum", 55.0);
        $this->RegisterPropertyFloat("DehumidifierPowerThreshold", 10.0);
        $this->RegisterPropertyInteger("DehumidifierPowerTime", 60);
        
        $this->RegisterPropertyFloat("TargetTemperature", 18.0);
        $this->RegisterPropertyFloat("VentilationThreshold", 0.5);
        $this->RegisterPropertyFloat("VentilationCloseMargin", 0.3);
        
        // Variables
        if (!IPS_VariableProfileExists('SmartClimate.VentilationRecommendation')) {
            IPS_CreateVariableProfile('SmartClimate.VentilationRecommendation', 0);
            IPS_SetVariableProfileAssociation('SmartClimate.VentilationRecommendation', false, 'Nicht Lüften!', '', -1);
            IPS_SetVariableProfileAssociation('SmartClimate.VentilationRecommendation', true, 'Lüften!', '', -1);
        }
        $this->RegisterVariableBoolean("VentilationRecommendation", "Lüften empfohlen!", "SmartClimate.VentilationRecommendation");
        $this->RegisterVariableString("VentilationDetails", "Hinweis");
        IPS_SetIcon($this->GetIDForIdent('VentilationDetails'), 'Wind');
        
        $this->RegisterVariableFloat("DewPointInside", "Taupunkt Keller", "~Temperature");
        IPS_SetIcon($this->GetIDForIdent('DewPointInside'), 'Drop');
        $this->RegisterVariableFloat("DewPointOutside", "Taupunkt Außen", "~Temperature");
        IPS_SetIcon($this->GetIDForIdent('DewPointOutside'), 'Drop');
        
        $this->RegisterVariableFloat("AbsHumInside", "Absolute Feuchte Keller", "");
        IPS_SetIcon($this->GetIDForIdent('AbsHumInside'), 'Drop');
        $this->RegisterVariableFloat("AbsHumOutside", "Absolute Feuchte Außen", "");
        IPS_SetIcon($this->GetIDForIdent('AbsHumOutside'), 'Drop');
        // Current values and Thresholds
        $this->RegisterVariableFloat("CurrentHumidity", "Aktuelle Luftfeuchtigkeit", "~Humidity.F");
        IPS_SetIcon($this->GetIDForIdent('CurrentHumidity'), 'Drop');
        
        // Custom profile for thresholds (5% steps)
        
        
        $this->RegisterVariableFloat("DehumidifierMaxHum", "Einschaltschwelle (Max %)", "BC.HumThreshold");
        IPS_SetIcon($this->GetIDForIdent('DehumidifierMaxHum'), 'Drop');
        $this->EnableAction("DehumidifierMaxHum");
        
        $this->RegisterVariableFloat("DehumidifierMinHum", "Ausschaltschwelle (Min %)", "BC.HumThreshold");
        IPS_SetIcon($this->GetIDForIdent('DehumidifierMinHum'), 'Drop');
        $this->EnableAction("DehumidifierMinHum");
        
        // Status of Dehumidifier
        
        $this->RegisterVariableInteger("DehumidifierStatus", "Status Entfeuchter", "BC.DehumidifierStatus");
        IPS_SetIcon($this->GetIDForIdent('DehumidifierStatus'), 'Drop');
        
        if (!IPS_VariableProfileExists('SmartClimate.DehumidifierStatus')) {
            IPS_CreateVariableProfile('SmartClimate.DehumidifierStatus', 1);
            IPS_SetVariableProfileAssociation('SmartClimate.DehumidifierStatus', 0, 'Aus', 'Sleep', 0x00FF00);
            IPS_SetVariableProfileAssociation('SmartClimate.DehumidifierStatus', 1, 'Entfeuchten', 'Drops', 0x0000FF);
            IPS_SetVariableProfileAssociation('SmartClimate.DehumidifierStatus', 2, 'Pausiert (Fenster offen)', 'Window', 0xFFFF00);
            IPS_SetVariableProfileAssociation('SmartClimate.DehumidifierStatus', 3, 'Pausiert (Tank voll)', 'Warning', 0xFF0000);
        }
        IPS_SetVariableCustomProfile($this->GetIDForIdent('DehumidifierStatus'), 'SmartClimate.DehumidifierStatus');
        
        // Tank Alarm Variable with Action Script to Acknowledge
        $this->RegisterVariableBoolean("AlarmTankFull", "Alarm: Wassertank voll", "");
        IPS_SetIcon($this->GetIDForIdent('AlarmTankFull'), 'Warning');
        $this->EnableAction("AlarmTankFull");
        
        $this->RegisterVariableBoolean("AlarmWindowClose", "Alarm: Fenster schließen", "");
        IPS_SetIcon($this->GetIDForIdent('AlarmWindowClose'), 'Warning');
        $this->EnableAction("AlarmWindowClose");
        
        // Timers
        $this->RegisterTimer("PowerCheckTimer", 0, 'BC_CheckPowerThreshold($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void{
        parent::ApplyChanges();
        // --- Auto-generated References ---
        foreach ($this->GetReferenceList() as $refID) {
            $this->UnregisterReference($refID);
        }
        $ref_SensorTempOutside = $this->ReadPropertyInteger('SensorTempOutside');
        if ($ref_SensorTempOutside > 1 && @IPS_ObjectExists($ref_SensorTempOutside)) {
            $this->RegisterReference($ref_SensorTempOutside);
        }
        $ref_SensorHumOutside = $this->ReadPropertyInteger('SensorHumOutside');
        if ($ref_SensorHumOutside > 1 && @IPS_ObjectExists($ref_SensorHumOutside)) {
            $this->RegisterReference($ref_SensorHumOutside);
        }
        $ref_SensorTempInside = $this->ReadPropertyInteger('SensorTempInside');
        if ($ref_SensorTempInside > 1 && @IPS_ObjectExists($ref_SensorTempInside)) {
            $this->RegisterReference($ref_SensorTempInside);
        }
        $ref_SensorHumInside = $this->ReadPropertyInteger('SensorHumInside');
        if ($ref_SensorHumInside > 1 && @IPS_ObjectExists($ref_SensorHumInside)) {
            $this->RegisterReference($ref_SensorHumInside);
        }
        $ref_ActuatorDehumidifierPlug = $this->ReadPropertyInteger('ActuatorDehumidifierPlug');
        if ($ref_ActuatorDehumidifierPlug > 1 && @IPS_ObjectExists($ref_ActuatorDehumidifierPlug)) {
            $this->RegisterReference($ref_ActuatorDehumidifierPlug);
        }
        $ref_SensorDehumidifierPower = $this->ReadPropertyInteger('SensorDehumidifierPower');
        if ($ref_SensorDehumidifierPower > 1 && @IPS_ObjectExists($ref_SensorDehumidifierPower)) {
            $this->RegisterReference($ref_SensorDehumidifierPower);
        }
        $ref_ActuatorRadiator1 = $this->ReadPropertyInteger('ActuatorRadiator1');
        if ($ref_ActuatorRadiator1 > 1 && @IPS_ObjectExists($ref_ActuatorRadiator1)) {
            $this->RegisterReference($ref_ActuatorRadiator1);
        }
        $ref_ActuatorRadiator2 = $this->ReadPropertyInteger('ActuatorRadiator2');
        if ($ref_ActuatorRadiator2 > 1 && @IPS_ObjectExists($ref_ActuatorRadiator2)) {
            $this->RegisterReference($ref_ActuatorRadiator2);
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


        
        // Unregister all messages first
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }
        
        // Register messages for sensors
        $sensors = [
            "SensorTempOutside", "SensorHumOutside", 
            "SensorTempInside", "SensorHumInside"
        ];
        
        foreach ($sensors as $sensorName) {
            $id = $this->ReadPropertyInteger($sensorName);
            if ($id > 0 && IPS_VariableExists($id)) {
                $this->RegisterMessage($id, VM_UPDATE);
            }
        }
        
        // Register messages for window sensors
        $windows = json_decode($this->ReadPropertyString("SensorWindows"), true);
        if (is_array($windows)) {
            foreach ($windows as $w) {
                $vid = $w['VariableID'] ?? 0;
                if ($vid > 0 && IPS_VariableExists($vid)) {
                    $this->RegisterMessage($vid, VM_UPDATE);
                }
            }
        }
        
        // Register message for Power Sensor
        $powerId = $this->ReadPropertyInteger("SensorDehumidifierPower");
        if ($powerId > 0 && IPS_VariableExists($powerId)) {
            $this->RegisterMessage($powerId, VM_UPDATE);
        }
        
        $iconMap = [
            'VentilationRecommendation' => 'Wind',
            'VentilationDetails' => 'Wind',
            'DewPointInside' => 'Drop',
            'DewPointOutside' => 'Drop',
            'AbsHumInside' => 'Drop',
            'AbsHumOutside' => 'Drop',
            'CurrentHumidity' => 'Drop',
            'DehumidifierMaxHum' => 'Drop',
            'DehumidifierMinHum' => 'Drop',
            'DehumidifierStatus' => 'Drop',
            'AlarmTankFull' => 'Warning',
            'AlarmWindowClose' => 'Warning'
        ];
        
        foreach ($iconMap as $ident => $icon) {
            if (@IPS_GetObjectIDByIdent($ident, $this->InstanceID) !== false) {
                IPS_SetVariableCustomPresentation($this->GetIDForIdent($ident), [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'ICON'         => $icon
                ]);
            }
        }
        
        // Initial Update
        $this->UpdateClimate();
    }
    
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void{
        $powerId = $this->ReadPropertyInteger("SensorDehumidifierPower");
        
        if ($SenderID == $powerId) {
            $this->HandlePowerUpdate($Data[0]);
        } else {
            $this->UpdateClimate();
        }
    }
    
    public function RequestAction(string $Ident, $Value): void{
        switch ($Ident) {
            case "AlarmTankFull":
            case "AlarmWindowClose":
                // Acknowledge the alarm (set to false)
                if ($Value == false) {
                    $this->SetValue($Ident, false);
                    $this->UpdateClimate();
                }
                break;
            case "DehumidifierMaxHum":
            case "DehumidifierMinHum":
                $this->SetValue($Ident, $Value);
                $this->UpdateClimate();
                break;
            default:
                throw new Exception("Invalid Ident");
        }
    }

    public function UpdateClimate()
    {
        $tempOut = $this->GetVarValue("SensorTempOutside");
        $humOut = $this->GetVarValue("SensorHumOutside");
        $tempIn = $this->GetVarValue("SensorTempInside");
        $humIn = $this->GetVarValue("SensorHumInside");
        
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
        
        if ($tempIn !== null && $humIn !== null) {
            $this->SetValue("CurrentHumidity", $humIn);
            
            // Dehumidifier Logic requires only inside sensors
            $this->ControlDehumidifier($humIn, $windowOpen);
        }
        
        if ($tempOut !== null && $humOut !== null && $tempIn !== null && $humIn !== null) {
            // Calculate Absolute Humidity and Dew Point
            $absOut = $this->CalculateAbsoluteHumidity($tempOut, $humOut);
            $dpOut = $this->CalculateDewPoint($tempOut, $humOut);
            
            $absIn = $this->CalculateAbsoluteHumidity($tempIn, $humIn);
            $dpIn = $this->CalculateDewPoint($tempIn, $humIn);
            
            $this->SetValue("AbsHumOutside", $absOut);
            $this->SetValue("DewPointOutside", $dpOut);
            
            $this->SetValue("AbsHumInside", $absIn);
            $this->SetValue("DewPointInside", $dpIn);
            
            // Ventilation logic
            $threshold = $this->ReadPropertyFloat("VentilationThreshold");
            $closeMargin = $this->ReadPropertyFloat("VentilationCloseMargin");
            $recommendation = false;
            $closeAlarm = false;
            $details = "Keine Aktion erforderlich.";
            
            if (!$windowOpen) {
                if ($absOut <= ($absIn - $threshold)) {
                    $recommendation = true;
                    $details = sprintf("Lüften empfohlen! Außen ist trockener (Außen: %.2f g/m³, Innen: %.2f g/m³)", $absOut, $absIn);
                } else {
                    $details = sprintf("Lüften lohnt nicht (Außen: %.2f g/m³, Innen: %.2f g/m³)", $absOut, $absIn);
                }
                // Automatically clear close alarm if window is closed
                $this->SetValueIfChanged("AlarmWindowClose", false);
            } else {
                if ($absOut >= ($absIn - $closeMargin)) {
                    $closeAlarm = true;
                    if ($absOut >= $absIn) {
                        $details = sprintf("Fenster SCHLIESSEN! Außen wird es feuchter (Außen: %.2f g/m³, Innen: %.2f g/m³)", $absOut, $absIn);
                    } else {
                        $details = sprintf("Achtung: Fenster bald schließen! Außenfeuchte nähert sich der Innenfeuchte (Außen: %.2f g/m³, Innen: %.2f g/m³)", $absOut, $absIn);
                    }
                } else {
                    $details = sprintf("Lüften trocknet weiterhin (Außen: %.2f g/m³, Innen: %.2f g/m³)", $absOut, $absIn);
                }
                
                if ($closeAlarm) {
                    $this->SetValueIfChanged("AlarmWindowClose", true);
                }
            }
            
            $this->SetValueIfChanged("VentilationRecommendation", $recommendation);
            $this->SetValueIfChanged("VentilationDetails", $details);
        }
        
        // Heating Logic
        $this->ControlHeating($humIn);
    }
    
    private function ControlDehumidifier($humIn, $windowOpen)
    {
        $plugId = $this->ReadPropertyInteger("ActuatorDehumidifierPlug");
        if ($plugId == 0 || !IPS_VariableExists($plugId)) return;
        
        $maxHum = $this->GetValue("DehumidifierMaxHum");
        $minHum = $this->GetValue("DehumidifierMinHum");
        $tankFull = $this->GetValue("AlarmTankFull");
        
        $plugStatus = GetValue($plugId);
        $newStatus = $plugStatus;
        $statusText = 0; // 0=Off, 1=On, 2=Window Open, 3=Tank Full
        
        if ($windowOpen) {
            $newStatus = false;
            $statusText = 2;
        } else {
            if ($humIn >= $maxHum) {
                $newStatus = true;
            } elseif ($humIn <= $minHum) {
                $newStatus = false;
            } else {
                $newStatus = $plugStatus;
            }
            
            if ($tankFull) {
                $statusText = 3;
            } else {
                $statusText = $newStatus ? 1 : 0;
            }
        }
        
        if ($plugStatus != $newStatus) {
            RequestAction($plugId, $newStatus);
        }
        
        $this->SetValueIfChanged("DehumidifierStatus", $statusText);
        $this->SetValueIfChanged("AlarmTankFull", $tankFull);
    }
    
    private function ControlHeating($humIn)
    {
        $rad1 = $this->ReadPropertyInteger("ActuatorRadiator1");
        $rad2 = $this->ReadPropertyInteger("ActuatorRadiator2");
        $targetBase = $this->ReadPropertyFloat("TargetTemperature");
        
        // Anti-Mold Logic: If humidity is extremely high, raise temp by 2 degrees
        $targetTemp = $targetBase;
        if ($humIn > 70.0) {
            $targetTemp += 2.0;
        }
        
        if ($rad1 > 0 && IPS_VariableExists($rad1)) {
            $currentRad1 = GetValue($rad1);
            if ($currentRad1 != $targetTemp) {
                @RequestAction($rad1, $targetTemp);
            }
        }
        
        if ($rad2 > 0 && IPS_VariableExists($rad2)) {
            $currentRad2 = GetValue($rad2);
            if ($currentRad2 != $targetTemp) {
                @RequestAction($rad2, $targetTemp);
            }
        }
    }
    
    private function HandlePowerUpdate($currentPower)
    {
        $plugId = $this->ReadPropertyInteger("ActuatorDehumidifierPlug");
        if ($plugId == 0) return;
        
        $plugStatus = GetValue($plugId);
        $threshold = $this->ReadPropertyFloat("DehumidifierPowerThreshold");
        $timeLimit = $this->ReadPropertyInteger("DehumidifierPowerTime");
        
        // We only care if the plug is logically ON
        if ($plugStatus) {
            if ($currentPower < $threshold) {
                // If timer is not running, start it
                $timerData = $this->GetTimerInterval("PowerCheckTimer");
                if ($timerData == 0 && !$this->GetValue("AlarmTankFull")) {
                    $this->SetTimerInterval("PowerCheckTimer", $timeLimit * 1000);
                }
            } else {
                // Power is fine, stop timer
                $this->SetTimerInterval("PowerCheckTimer", 0);
                
                // If tank was full, auto reset the alarm
                if ($this->GetValue("AlarmTankFull")) {
                    $this->SetValue("AlarmTankFull", false);
                    $this->UpdateClimate();
                }
            }
        } else {
            $this->SetTimerInterval("PowerCheckTimer", 0);
        }
    }
    
    public function CheckPowerThreshold()
    {
        $this->SetTimerInterval("PowerCheckTimer", 0); // Stop timer
        
        // If we reach this, the power was below threshold for X seconds while the plug was ON.
        $this->SetValue("AlarmTankFull", true);
        
        // Dehumidifier Logic will catch this on next update, let's force it
        $this->UpdateClimate();
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
    
    private function CalculateDewPoint($t, $rh)
    {
        if ($t < 0) {
            $a = 7.6; $b = 240.7;
        } else {
            $a = 7.5; $b = 237.3;
        }
        $sdd = 6.1078 * pow(10, ($a * $t) / ($b + $t));
        $dd = $sdd * ($rh / 100);
        $v = log10($dd / 6.1078);
        return ($b * $v) / ($a - $v);
    }
    
    private function CalculateAbsoluteHumidity($t, $rh)
    {
        if ($t < 0) {
            $a = 7.6; $b = 240.7;
        } else {
            $a = 7.5; $b = 237.3;
        }
        $sdd = 6.1078 * pow(10, ($a * $t) / ($b + $t));
        $dd = $sdd * ($rh / 100);
        return 100000 * 18.016 / 8314.3 * $dd / ($t + 273.15);
    }
    
    private function SetValueIfChanged($Ident, $Value)
    {
        $id = $this->GetIDForIdent($Ident);
        if (GetValue($id) !== $Value) {
            SetValue($id, $Value);
        }
    }

    protected function LogMessage(string $Message, int $Type): bool
    {
        IPS_LogMessage('SmartVillaKunterbunt', 'BasementClimate: '. $Message);
        return true;
    }

    public function GetConfigurationForm(): string
    {
        return <<<'EOT'
{
    "elements": [
        {
            "type": "ExpansionPanel",
            "caption": "⚙ Sensoren (Außen)",
            "items": [
                {
                    "type": "RowLayout",
                    "items": [
                        {
                            "type": "SelectVariable",
                            "name": "SensorTempOutside",
                            "caption": "Temperatur Außen"
                        },
                        {
                            "type": "SelectVariable",
                            "name": "SensorHumOutside",
                            "caption": "Feuchtigkeit Außen"
                        }
                    ]
                },
                {
                    "type": "Label",
                    "caption": "Sensoren (Innen/Keller)"
                },
                {
                    "type": "RowLayout",
                    "items": [
                        {
                            "type": "SelectVariable",
                            "name": "SensorTempInside",
                            "caption": "Temperatur Keller"
                        },
                        {
                            "type": "SelectVariable",
                            "name": "SensorHumInside",
                            "caption": "Feuchtigkeit Keller"
                        }
                    ]
                }
            ]
        },
        {
            "type": "List",
            "name": "SensorWindows",
            "caption": "Fenster-/Türkontakte (Keller)",
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
            "name": "ActuatorDehumidifierPlug",
            "caption": "Schaltsteckdose Entfeuchter"
        },
        {
            "type": "SelectVariable",
            "name": "SensorDehumidifierPower",
            "caption": "Leistungsmessung Entfeuchter (Watt)"
        },
        {
            "type": "SelectVariable",
            "name": "ActuatorRadiator1",
            "caption": "Heizkörper 1 (Solltemperatur)"
        },
        {
            "type": "SelectVariable",
            "name": "ActuatorRadiator2",
            "caption": "Heizkörper 2 (Solltemperatur)"
        },
        {
            "type": "Label",
            "caption": "Einstellungen Entfeuchter\n\nDas Modul steuert den Entfeuchter automatisch basierend auf der Kellerfeuchtigkeit (Ausschalt- und Einschaltschwelle). Ist ein Fenster geöffnet, pausiert der Entfeuchter automatisch. Wird über die Leistungsmessung festgestellt, dass der Entfeuchter läuft, aber über eine bestimmte Dauer fast keinen Strom verbraucht (z. B. Standby), erkennt das Modul, dass der Wassertank voll ist, und schlägt Alarm."
        },
        {
            "type": "NumberSpinner",
            "name": "DehumidifierPowerThreshold",
            "caption": "Schwellwert für Tank-Voll-Erkennung (Watt)",
            "digits": 1
        },
        {
            "type": "NumberSpinner",
            "name": "DehumidifierPowerTime",
            "caption": "Dauer für Grenzwert (Sekunden)"
        },
        {
            "type": "Label",
            "caption": "Einstellungen Heizung\n\nAnti-Schimmel-Logik: Die hier definierten Heizkörper werden regulär auf die 'Basis-Solltemperatur' geregelt. Steigt die Luftfeuchtigkeit im Keller jedoch auf kritische Werte (> 70%), wird die Solltemperatur automatisch um 2°C erhöht. Wärmere Luft kann mehr Feuchtigkeit aufnehmen, was der Schimmelbildung aktiv entgegenwirkt."
        },
        {
            "type": "NumberSpinner",
            "name": "TargetTemperature",
            "caption": "Basis-Solltemperatur (°C)",
            "digits": 1
        },
        {
            "type": "Label",
            "caption": "Einstellungen Lüftungsempfehlung\n\nDas Modul vergleicht kontinuierlich die absolute Feuchtigkeit (g/m³) von Innen und Außen. Es gibt eine Lüftungsempfehlung ab, wenn es draußen trocken genug ist (Differenz-Schwellwert). Wenn das Fenster offen ist und die Außenluft feuchter wird als die Innenluft, warnt das Modul rechtzeitig (Puffer), um das Fenster wieder zu schließen und den Keller vor weiterer Feuchtigkeit zu schützen."
        },
        {
            "type": "NumberSpinner",
            "name": "VentilationThreshold",
            "caption": "Mindest-Differenz (g/m³) für Lüftung",
            "digits": 1
        },
        {
            "type": "NumberSpinner",
            "name": "VentilationCloseMargin",
            "caption": "Puffer (g/m³) für Schließ-Warnung",
            "digits": 1
        }
    ]
}
EOT;
    }
}


