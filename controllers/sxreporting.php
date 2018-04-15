<?php

class SxReporting extends OBFController
{
  public function generate()
  {
    $this->user->require_permission('sx_reporting_module');
    
    $reporting_model = $this->load->model('SxReporting');
    
    $data = [
      'device_id' => $this->data('device'),
      'start' => trim($this->data('start')),
      'end' => trim($this->data('end')),
      'service_name' => trim($this->data('service_name')),
      'transmission_category' => trim($this->data('transmission_category')),
      'media_category' => $this->data('media_category'),
      'isrc' => $this->data('isrc'),
      'label' => $this->data('label'),
      'tuning_hours' => trim($this->data('tuning_hours'))
    ];
    
    $validation = $reporting_model('validate',$data);
    if($validation[0]==false) return $validation;
    
    $report_csv = $reporting_model('generate',$data);
    
    return array(true,'Report generated. Click the download button below.',$report_csv);
  }
}