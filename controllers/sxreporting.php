<?php

class SxReporting extends OBFController
{
  public function generate()
  {
    $this->user->require_permission('sx_reporting_module');
    
    $models = OBFModels::get_instance();

    $data = [
      'player_id' => $this->data('player'),
      'start' => trim($this->data('start')),
      'end' => trim($this->data('end')),
      'service_name' => trim($this->data('service_name')),
      'transmission_category' => trim($this->data('transmission_category')),
      'media_category' => $this->data('media_category'),
      'isrc' => $this->data('isrc'),
      'label' => $this->data('label'),
      'tuning_hours' => trim($this->data('tuning_hours')),
      'additional_fields' => $this->data('additional_fields')
    ];

    $validation = $models->SxReporting('validate', $data);
    if($validation[0]==false) return $validation;

    $report_csv = $models->SxReporting('generate', $data);

    if($report_csv[1]==0) return [false,'No log data found for this reporting period.'];

    return array(true,'Report generated ('.$report_csv[1].' items). Click the download button below.',$report_csv[0]);
  }
}
