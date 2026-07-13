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
        
        // Variables
        $this->RegisterVariableBoolean("VentilationRecommendation", "Lüften empfohlen!", "~Switch");
        $this->RegisterVariableString("VentilationDetails", "Hinweis");
        
        $this->RegisterVariableFloat("DewPointInside", "Taupunkt Keller", "~Temperature");
        $this->RegisterVariableFloat("DewPointOutside", "Taupunkt Außen", "~Temperature");
        
        $this->RegisterVariableFloat("AbsHumInside", "Absolute Feuchte Keller", "");
        $this->RegisterVariableFloat("AbsHumOutside", "Absolute Feuchte Außen", "");
        // Current values and Thresholds
        $this->RegisterVariableFloat("CurrentHumidity", "Aktuelle Luftfeuchtigkeit", "~Humidity.F");
        
        // Custom profile for thresholds (5% steps)
        
        
        $this->RegisterVariableFloat("DehumidifierMaxHum", "Einschaltschwelle (Max %)", "BC.HumThreshold");
        $this->EnableAction("DehumidifierMaxHum");
        
        $this->RegisterVariableFloat("DehumidifierMinHum", "Ausschaltschwelle (Min %)", "BC.HumThreshold");
        $this->EnableAction("DehumidifierMinHum");
        
        // Status of Dehumidifier
        
        $this->RegisterVariableInteger("DehumidifierStatus", "Status Entfeuchter", "BC.DehumidifierStatus");
        
                IPS_SetVariableCustomPresentation($this->GetIDForIdent("HumidityThreshold"), json_encode([
            'MIN' => 30.0,
            'MAX' => 80.0,
            'STEP' => 1.0,
            'SUFFIX' => ' %',
            'ICON' => 'Drops'
        ]));

        IPS_SetVariableCustomPresentation($this->GetIDForIdent("DehumidifierStatus"), json_encode([
            'ASSOCIATIONS' => [
                ['VALUE' => 0, 'NAME' => 'Aus', 'ICON' => 'Sleep', 'COLOR' => 0x00FF00],
                ['VALUE' => 1, 'NAME' => 'Entfeuchten', 'ICON' => 'Drops', 'COLOR' => 0x0000FF],
                ['VALUE' => 2, 'NAME' => 'Pausiert (Fenster offen)', 'ICON' => 'Window', 'COLOR' => 0xFFFF00],
                ['VALUE' => 3, 'NAME' => 'Pausiert (Tank voll)', 'ICON' => 'Warning', 'COLOR' => 0xFF0000]
            ]
        ]));
        
        // Tank Alarm Variable with Action Script to Acknowledge
        $this->RegisterVariableBoolean("AlarmTankFull", "Alarm: Wassertank voll", "~Alert");
        $this->EnableAction("AlarmTankFull");
        
        $this->RegisterVariableBoolean("AlarmWindowClose", "Alarm: Fenster schließen", "~Alert");
        $this->EnableAction("AlarmWindowClose");
        
        // Timers
        $this->RegisterTimer("PowerCheckTimer", 0, 'BC_CheckPowerThreshold($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void{
        parent::ApplyChanges();
        
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
                if ($absOut >= $absIn) {
                    $closeAlarm = true;
                    $details = sprintf("⚠️ Fenster SCHLIESSEN! Außen wird es feuchter (Außen: %.2f g/m³, Innen: %.2f g/m³)", $absOut, $absIn);
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
        } elseif ($tankFull) {
            $newStatus = false;
            $statusText = 3;
        } else {
            if ($humIn >= $maxHum) {
                $newStatus = true;
                $statusText = 1;
            } elseif ($humIn <= $minHum) {
                $newStatus = false;
                $statusText = 0;
            } else {
                $statusText = $plugStatus ? 1 : 0;
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
                RequestAction($rad1, $targetTemp);
            }
        }
        
        if ($rad2 > 0 && IPS_VariableExists($rad2)) {
            $currentRad2 = GetValue($rad2);
            if ($currentRad2 != $targetTemp) {
                RequestAction($rad2, $targetTemp);
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
                if ($timerData == 0) {
                    $this->SetTimerInterval("PowerCheckTimer", $timeLimit * 1000);
                }
            } else {
                // Power is fine, stop timer
                $this->SetTimerInterval("PowerCheckTimer", 0);
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
            $targetBool = ($closedValue === 'true' || $closedValue === '1' || strtolower((string)$closedValue) === 'wahr');
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
        IPS_LogMessage('SmartVillaKunterbunt', 'BasementClimate: ' . $Message);
        return true;
    }
}

