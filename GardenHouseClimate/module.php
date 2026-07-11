<?php

declare(strict_types=1);

class GardenHouseClimate extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Properties
        $this->RegisterPropertyInteger("SensorTempInside", 0);
        $this->RegisterPropertyInteger("SensorTempOutside", 0);
        $this->RegisterPropertyInteger("SensorWindow", 0);
        
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
        
        $this->RegisterVariableInteger("HeaterStatus", "Status Heizung", "");
        IPS_SetVariableCustomPresentation($this->GetIDForIdent("HeaterStatus"), '{"Type":"Label","Format":"%s"}');
        
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

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }
        
        $sensors = [
            "SensorTempInside", "SensorTempOutside", "SensorWindow"
        ];
        
        foreach ($sensors as $sensorName) {
            $id = $this->ReadPropertyInteger($sensorName);
            if ($id > 0 && IPS_VariableExists($id)) {
                $this->RegisterMessage($id, VM_UPDATE);
            }
        }
        
        $powerId = $this->ReadPropertyInteger("SensorHeaterPower");
        if ($powerId > 0 && IPS_VariableExists($powerId)) {
            $this->RegisterMessage($powerId, VM_UPDATE);
        }
        
        $this->UpdateClimate();
    }
    
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $powerId = $this->ReadPropertyInteger("SensorHeaterPower");
        $windowId = $this->ReadPropertyInteger("SensorWindow");
        
        if ($SenderID == $powerId) {
            $this->HandlePowerUpdate($Data[0]);
        } elseif ($SenderID == $windowId) {
            $this->HandleWindowUpdate($Data[0]);
            $this->UpdateClimate();
        } else {
            $this->UpdateClimate();
        }
    }
    
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
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
        $windowOpen = $this->GetVarValue("SensorWindow");
        
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
        $targetTemp = $this->ReadPropertyFloat("TargetTemperature");
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
}
