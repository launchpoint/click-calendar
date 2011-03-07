<?php

class icalWriter {
  var $config;
  var $calendar;
  var $error;
  var $message;
  var $param;
  var $starttime;
  var $total;
  
  
  /*
  Aaron Diers
  circa October 2009
  
  Ok, after some working but poorly written attempts, this is looking better.
  To write to an ical, you'll need to pass in setup as in icalParser:
  
  $setup = array(
      'DIR'   => ICAL_DATA_FPATH,
      'FILE'  => 'myfile.ics'
  );
  
  Then, pass in an array of valid vevents, with model information like so:
  array (
    [0] => array(
        [vevent] => vevent object,
        [model] => 'MyClass',
        [id] => 27 - database id
        [ical_uid] => null or existing ical uid
        ),
      
    // This form is used to delete a vevent from the ical file:
    [1] => array([ical_uid] => 'some existing value')
    
    ...
  )
  
  The model/id combination allows the code to retrieve the model via ActiveRecord.
  The model must have an attribute named ical_uid to keep track of the unique id generated for ical vevents.
  
  */
  
  function icalWriter ($setup, $vevents = null, $args = null) {
            /** initiate */
    $this->starttime = microtime( TRUE );
    $this->error     = FALSE;
    $this->message   = array();
    $this->config    = $setup;

    //$this->fixinput( $input );
    $this->total       = 0;
    $this->calendar    = new vcalendar();
    $this->calendar->setConfig( 'unique_id', "click_ical_parser");

    $this->calendar->setConfig( 'directory', $this->config['DIR']);
    $this->calendar->setConfig( 'filename', $this->config['FILE'] );
    
    $this->calendar->parse(); // In case the file already exists, so we don't lose what's already there
    
    if(is_array($vevents))
    {
        $deletes = array();
        
        // First, process existing iCal vevents in the file
        while ($vevent = $this->calendar->getComponent('vevent')) {
            $uid = $vevent->getProperty('uid');
            
            // Look for the one that matches the uid
            foreach($vevents as $vevent_index => $vevent_entry)
            {
                
                if ($vevent_entry['ical_uid'] == $uid)
                {

                    $updated_vevent = null;
                    if (isset($vevent_entry['vevent'])) $updated_vevent = $vevent_entry['vevent'];
                    
                    if (isset($updated_vevent))
                    {
                        // Update the calendar with the new version of the vevent
                        $this->calendar->setComponent($updated_vevent, $uid);
                        $this->calendar->saveCalendar();
                        eval("\$record = ".$vevent_entry['model']."::find_by_id('".$vevent_entry['id']."');");
                        $record->ical_uid = $updated_vevent->uid['value'];
                        $record->save();
                    }
                    else
                    {
                        $deletes[] = $uid;
                    }
                }
            }
        }
        
        foreach($deletes as $delete_uid)
        {
            $this->calendar->deleteComponent($delete_uid);
        }
        $this->calendar->saveCalendar();
        
        //Process the new events and set their ical_uid values.
        foreach($vevents as $vevent_entry)
        {
            if(!$vevent_entry['ical_uid'])
            {
                $vevent = $vevent_entry['vevent'];
                $this->calendar->setComponent($vevent);
                $this->calendar->saveCalendar();
                eval("\$record = ".$vevent_entry['model']."::find_by_id('".$vevent_entry['id']."');");
                $record->ical_uid = $vevent->uid['value'];
                $record->save();
            }
        }
        $this->calendar->saveCalendar();

    }
    
  }
}