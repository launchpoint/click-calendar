<?php
class icalParser {
  var $config;
  var $calendar;
  var $error;
  var $message;
  var $param;
  var $starttime;
  var $total;
  
  /*
  Aaron Diers
  Added an extra item to the $setup array.
  icalEventClass - pass the name of a callback function to be called on the event (below) to add a CSS class to the event div
  */
  function icalParser ( $setup) {
            /** initiate */
    $this->starttime = microtime( TRUE );
    $this->error     = FALSE;
    $this->message   = array();
    $this->config    = $setup;

    //$this->fixinput( $input );
    $this->total       = 0;
    $this->calendar    = new vcalendar();
    $this->calendar->setConfig( 'unique_id', "click_ical_parser");

            /** manage the call */

    $this->calendar->setConfig( 'directory', $this->config['DIR']);
    $this->calendar->setConfig( 'filename', $this->config['FILE'] );
    $this->calendar->parse();
    if( $this->calendar->parse() ) {
      
    }

    $this->componentSelect($this->calendar);
  }


  function componentSelect() {
    //$sfr = mktime( 0, 0, 0, (int) 9, ((int) 1) - 1, (int) 2009 );
    //$sto = mktime( 0, 0, 0, (int) 9, ((int) 30) + 1, (int) 2009 );
    $sfr = $this->config['START'];
    $sto = $this->config['END'];
    $components = $this->calendar->selectComponents( (int) date( 'Y', $sfr ), (int) date( 'm', $sfr ), (int) date( 'd', $sfr )
                                                   , (int) date( 'Y', $sto ), (int) date( 'm', $sto ), (int) date( 'd', $sto ), 'vevent', false, true, true);

    if(!$components) return;                                        
    foreach( $components as $yix => $year_arr ) {
     foreach( $year_arr as $mix => $month_arr ) {
      foreach( $month_arr as $dix => $day_arr ) {
       $hour_arr = array();
       foreach( $day_arr as $comp ) {
         if( $dtstart = $comp->getProperty( 'x-current-dtstart' ))
           $dtstart = $comp->_date_time_string( $dtstart[1] );
         else
           $dtstart = $comp->getProperty( 'dtstart' );
         if(( $dtstart['year'] != $yix ) || ( $dtstart['month'] != $mix ) || ( $dtstart['day'] != $dix ) || empty( $dtstart['hour'] ))
           $hour = 0; // i.e. not startdate
         else
           $hour = (int) $dtstart['hour'];
         $hour_arr[$hour][] = $comp;
       } // end day_arr
       ksort( $hour_arr );
       $components[$yix][$mix][$dix] = $hour_arr;
      } // end month_arr
     }  // end year_arr
    }   // end components
  
    $multievents=array();
    foreach( $components as $yix => $year_arr ) {
        foreach( $year_arr as $mix => $month_arr ) {
            foreach( $month_arr as $dix => $day_arr ) {
                foreach( $day_arr as $hix => $hour_arr ) {
                    $dayevents=array();
                    foreach( $hour_arr as $vevent ) {
                        $isrepeat = false;
                        $event=array();
                        $curstart = $vevent->getProperty( 'x-current-dtstart' );
                        $curend = $vevent->getProperty( 'x-current-dtend' );
                        if(!$curend)
                        {
                            $dtend = $vevent->getProperty( 'dtend' );
                            if(!$dtend)
                            {
                                $dtend = $vevent->getProperty( 'duration', false, false, true );
                            }
                            if(array_key_exists('hour', $dtend))
                            {
                              $dtend= date(DATE_ATOM,mktime($dtend['hour'], $dtend['min'], $dtend['sec'], $dtend['month'], $dtend['day'] , $dtend['year']));
                            }
                            else
                            {
                              $dtend= date(DATE_ATOM,mktime(0,0,0, $dtend['month'], $dtend['day'] , $dtend['year']));
                            }
                        }
                        else
                        {
                          $isrepeat = true;
                          $dtend = date(DATE_ATOM, strtotime($curend['1']));
                        }

                        if(!$curend)
                        {
                          $dtstart = $vevent->getProperty( 'dtstart' );
                          if(array_key_exists('hour', $dtstart))
                          {
                            $dtstart = date(DATE_ATOM,mktime($dtstart['hour'], $dtstart['min'], $dtstart['sec'], $dtstart['month'], $dtstart['day'] , $dtstart['year']));
                          }
                          else
                          {
                            $dtstart = date(DATE_ATOM,mktime(0, 0, 0, $dtstart['month'], $dtstart['day'] , $dtstart['year']));          
                          }
                        }
                        else
                        {
                          $isrepeat = true;
                          $dtstart= date(DATE_ATOM, strtotime($curstart['1']));
                        }
                        $summary = $vevent->getProperty( 'summary' );
                        $uid = $vevent->getProperty( 'uid' );
                        $description = $vevent->getProperty( 'description' );
                        $event['id'] = $uid;
                        
                        $event['title'] = $summary;
                        $event['start']=$dtstart;
                        $event['end']=$dtend;
                        
                        if (isset($this->config['icalEventClass']))
                        {
                            $className = call_user_func($this->config['icalEventClass'], $event);
                            if ($className) $event['className'] = $className;
                        }

                        if(!array_key_exists($uid, $dayevents))
                        {
                            if(!array_key_exists($uid, $multievents) || $isrepeat)
                            {
                              $events[] = $event;
                              $dayevents[$uid]=$event;
                              $multievents[$uid]=$event;
                            }
                        }
                    }
                }
            }
        }
    }
    echo json_encode($events);
  }
}