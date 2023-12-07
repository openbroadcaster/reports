<?php

class SxReportingModel extends OBFModel
{
  public function validate($data)
  {
    // make sure device id valid
    $device = $this->models->players('get_one',$data['device_id']);
    if(!$device) return [false,'Player not valid.'];
    
    // start/end validate
    $start = DateTime::createFromFormat('Y-m-d',$data['start']);
    $end = DateTime::createFromFormat('Y-m-d',$data['end']);
    if(!$start) return [false,'Start date not valid.'];
    if(!$end) return [false,'End date not valid.'];
    if(!$start>=$end) return [false,'Start time must be before end time.'];
    
    if($data['isrc'] && !$this->models->mediametadata('get_by_name',$data['isrc'])) return [false,'ISRC field is not valid.'];
    if($data['label'] && !$this->models->mediametadata('get_by_name',$data['label'])) return [false,'Marketing Label field is not valid.'];

    $allowed_additional = [
      'year',
      'category_name',
      'genre_name',
      'country_name',
      'language_name',
      'comments',
      'duration'
    ];

    $all_metadata_fields = $this->models->mediametadata('get_all');

    foreach($all_metadata_fields as $field)
    {
      $allowed_additional[] = 'metadata_'.$field['name'];
    }

    if(is_array($data['additional_fields']))
    {
      foreach($data['additional_fields'] as $field_name) 
        if(array_search($field_name,$allowed_additional)===false) return [false,'One or more additional fields are not valid.'];
    }
    
    if($data['tuning_hours']!='' && !preg_match('/^[0-9]+$/',$data['tuning_hours'])) return [false,'Tuning hours must be a number.'];
    
    return [true,'Valid.'];
  }
  
  public function generate($data)
  {  
    // get device timezone
    $device = $this->models->players('get_one',$data['device_id']);
    $timezone = new DateTimeZone($device['timezone']);
    
    // get start/end times
    $start = DateTime::createFromFormat('Y-m-d H:i:s',$data['start'].' 00:00:00',$timezone);
    $end = DateTime::createFromFormat('Y-m-d H:i:s',$data['end'].' 23:59:59',$timezone);
    
    // playlog timestamps use 2 decimal places
    $start_timestamp = $start->getTimestamp().'.00';
    $end_timestamp = $end->getTimestamp().'.99';
    
    // get media for which we have a media item assigned
    $this->db->query('SELECT media_id FROM playlog WHERE media_id!=0 AND player_id = '.$device['id'].' AND timestamp BETWEEN '.$start_timestamp.' AND '.$end_timestamp);
    
    $frequency = [];
    while($row = $this->db->assoc_row())
    {
      if($row['media_id']==0) continue;
      
      if(isset($frequency[$row['media_id']])) $frequency[$row['media_id']]++;
      else $frequency[$row['media_id']] = 1;
    }
    
    $rows = [];
    
    $data['service_name'] = trim($data['service_name']);
    $data['transmission_category'] = trim($data['transmission_category']);
    
    // if we have 'comments' in additional fields, move it to the end.
    if(is_array($data['additional_fields']) && ($comments_index = array_search('comments',$data['additional_fields']))!==FALSE)
    {
      unset($data['additional_fields'][$comments_index]);
      $data['additional_fields'][]='comments';
    }
    
    foreach($frequency as $media_id=>$frequency)
    {
      // try to get media info from regular media table
      $this->db->what('media.artist','artist');
      $this->db->what('media.title','title');
      $this->db->what('media.album','album');
      $this->db->what('media.category_id','category_id');
      
      if($data['isrc']) $this->db->what('media_metadata.'.$data['isrc'],'isrc');
      if($data['label']) $this->db->what('media_metadata.'.$data['label'],'label');
      
      // get additional field info from database
      if(is_array($data['additional_fields'])) foreach($data['additional_fields'] as $additional_field)
      {
        if($additional_field=='year') $this->db->what('media.year','year');
        elseif($additional_field=='comments') $this->db->what('media.comments','comments');
        elseif($additional_field=='duration') $this->db->what('media.duration','duration');
        elseif($additional_field=='category_name')
        {
          $this->db->what('media_categories.name','category_name');
          $this->db->leftjoin('media_categories','media.category_id','media_categories.id');
        }
        elseif($additional_field=='genre_name')
        {
          $this->db->what('media_genres.name','genre_name');
          $this->db->leftjoin('media_genres','media.genre_id','media_genres.id');
        }
        elseif($additional_field=='country_name')
        {
          $this->db->what('media_countries.name','country_name');
          $this->db->leftjoin('media_countries','media.country_id','media_countries.id');
        }
        elseif($additional_field=='language_name')
        {
          $this->db->what('media_languages.name','language_name');
          $this->db->leftjoin('media_languages','media.language_id','media_languages.id');
        }
        
        elseif(strpos($additional_field,'metadata_')===0)
        {
          $metadata_field_name = substr($additional_field,9);
          $this->db->what('media_metadata.'.$metadata_field_name,$additional_field);
        }
      }
      
      $this->db->where('media.id',$media_id);
      $this->db->leftjoin('media_metadata','media.id','media_metadata.media_id');
      $item = $this->db->get_one('media');
      
      // media item not found? try deleted media table
      if(!$item)
      {
        $this->db->where('media_id',$media_id);
        $item = $this->db->get_one('media_deleted');
        
        // not found in deleted media table? ignore.
        if(!$item) continue;
        
        // set item to deleted item metadata
        $item = json_decode($item['metadata'], true);
        if(isset($item['metadata_'.$data['isrc']])) $item['isrc'] = $item['metadata_'.$data['isrc']];
        if(isset($item['metadata_'.$data['label']])) $item['label'] = $item['metadata_'.$data['label']];
      }
      
      // do we have a category set for this item? if so, make sure matches category specified for report.
      if($data['media_category'] && $item['category_id'] && $item['category_id']!=$data['media_category']) continue;
    
      // add csv row
      $row = [
        $data['service_name'],
        $data['transmission_category'],
        isset($item['artist']) ? $item['artist'] : '',
        isset($item['title']) ? $item['title'] : '',
        isset($item['isrc']) ? $item['isrc'] : '',
        isset($item['album']) ? $item['album'] : '',
        isset($item['label']) ? $item['label'] : '',
        '',
        $data['tuning_hours'],
        $data['service_name'],
        $frequency
      ];
      
      // add additional field info to row
      if(is_array($data['additional_fields'])) 
      {
        foreach($data['additional_fields'] as $additional_field)
        {
          $row[] = isset($item[$additional_field]) ? $item[$additional_field] : '';
        }
      }
      
      $rows[] = $row;
    }
    
    // sort csv rows by artist, title
    usort($rows, function($a,$b)
    { 
      $artist_a = strtolower($a[2]);
      $artist_b = strtolower($b[2]);
      $title_a = strtolower($a[3]);
      $title_b = strtolower($b[3]);
    
      if($artist_a == $artist_b) return strcmp($title_a,$title_b);
      else return strcmp($artist_a,$artist_b);
    });
    
    // create CSV file
    $row_count = 0;
    
    $fh = fopen('php://temp','w+');
    
    $headings = [
      'NAME_OF_SERVICE',
      'TRANSMISSION_CATEGORY',
      'FEATURED_ARTIST',
      'SOUND_RECORDING_TITLE',	
      'ISRC',	
      'ALBUM_TITLE',
      'MARKETING_LABEL',
      'ACTUAL_TOTAL_PERFORMANCES',
      'AGGREGATE_TUNING_HOURS',
      'CHANNEL_OR_PROGRAM_NAME',
      'PLAY_FREQUENCY'
    ];
    
    // add additional field headings
    $additional_fields = [
      'year'=>'YEAR',
      'category_name'=>'CATEGORY',
      'genre_name'=>'GENRE',
      'country_name'=>'COUNTRY',
      'language_name'=>'LANGUAGE',
      'comments'=>'COMMENTS',
      'duration'=>'DURATION (SECONDS)'
    ];
    
    if(is_array($data['additional_fields'])) 
    {
      foreach($data['additional_fields'] as $field)
      {
        if(strpos($field,'metadata_')===0) 
        {
          $metadata_field = $this->models->mediametadata('get_by_name',substr($field,9));
          $headings[] = strtoupper($metadata_field['description']);
        }
        else $headings[] = $additional_fields[$field];
      }
    }
  
    fputcsv($fh, $headings);
    
    foreach($rows as $row) { fputcsv($fh, $row); $row_count++; }
    
    // get playlog entries with a title or artist, but no media_id and add to end of our report
    $this->db->query('SELECT artist, title FROM playlog WHERE (artist!="" OR title!="") AND (artist!="unknown" OR title!="unknown") AND context="fallback" AND media_id=0 AND player_id = '.$device['id'].' AND timestamp BETWEEN '.$start_timestamp.' AND '.$end_timestamp);
    
    $frequency = [];
    while($row = $this->db->assoc_row())
    {
      // create a identifier based on artist+title, also storing this data for later.
      $index = json_encode(['artist'=>$row['artist'],'title'=>$row['title']]);
      
      if(isset($frequency[$index])) $frequency[$index]++;
      else $frequency[$index] = 1;
    }
    
    $rows = [];
    foreach($frequency as $index=>$frequency)
    {
      $item = json_decode($index, true);
      $rows[] = [
        $data['service_name'],
        $data['transmission_category'],
        isset($item['artist']) ? $item['artist'] : '',
        isset($item['title']) ? $item['title'] : '',
        '',
        '',
        '',
        '',
        $data['tuning_hours'],
        $data['service_name'],
        $frequency
      ];
    }
    
    // sort csv rows by artist, title
    usort($rows, function($a,$b)
    { 
      $artist_a = strtolower($a[2]);
      $artist_b = strtolower($b[2]);
      $title_a = strtolower($a[3]);
      $title_b = strtolower($b[3]);
    
      if($artist_a == $artist_b) return strcmp($title_a,$title_b);
      else return strcmp($artist_a,$artist_b);
    });
    
    foreach($rows as $row) { fputcsv($fh, $row); $row_count++; }
    
    // get CSV file data for return
    $csv = stream_get_contents($fh, -1, 0);
    
    fclose($fh);
    
    return [$csv, $row_count];
  }
}