<?php
$form_add_message = "";
$form_error_message = "";
$form_delete_message = "";

function eme_payment_form($event,$booking_id) {
   if (empty($event)) {
      $event_id=eme_get_event_id_by_booking_id($booking_id);
      if ($event_id)
         $event = eme_get_event($event_id);
   }
   $booking = eme_get_booking($booking_id);
   if (!is_array($booking))
      return "";
   if ($booking['booking_payed'])
      return "<div id='eme-rsvp-message' class='eme-rsvp-message'>".__('This booking has already been payed for','eme')."</div>";

   if (is_array($event)) {
      $total_price=eme_get_total_booking_price($event,$booking);
      $ret_string = "<div id='eme-rsvp-message' class='eme-rsvp-message'>".__('Payment handling','eme')."</div>";
      $ret_string .= sprintf(__("The booking price in %s is: %d",'eme'),$event['currency'],$total_price);
      if ($event['use_paypal'])
         $ret_string .= eme_paypal_form($event,$booking_id);
      if ($event['use_2co'])
         $ret_string .= eme_2co_form($event,$booking_id);
      if ($event['use_webmoney'])
         $ret_string .= eme_webmoney_form($event,$booking_id);
      if ($event['use_google'])
         $ret_string .= eme_google_form($event,$booking_id);

      if ($event['use_paypal'] || $event['use_google'] || $event['use_2co'] || $event['use_webmoney'])
         return $ret_string;
      else
         return "";
   } else {
      return "";
   }
}

function eme_add_booking_form($event_id) {
   global $form_add_message, $form_error_message, $current_user;
   global $booking_id_done;

   $bookerName="";
   $bookerEmail="";
   $bookerComment="";
   $bookerPhone="";
   $bookedSeats=0;

   if (is_user_logged_in()) {
      get_currentuserinfo();
      $bookerName=$current_user->display_name;
      $bookerEmail=$current_user->user_email;
   }

   // check for previously filled in data
   // this in case people entered a wrong captcha
   if (isset($_POST['bookerName'])) $bookerName = eme_sanitize_html(eme_sanitize_request($_POST['bookerName']));
   if (isset($_POST['bookerEmail'])) $bookerEmail = eme_sanitize_html(eme_sanitize_request($_POST['bookerEmail']));
   if (isset($_POST['bookerPhone'])) $bookerPhone = eme_sanitize_html(eme_sanitize_request($_POST['bookerPhone']));
   if (isset($_POST['bookerComment'])) $bookerComment = eme_sanitize_html(eme_sanitize_request($_POST['bookerComment']));
   if (isset($_POST['bookedSeats'])) $bookedSeats = eme_sanitize_html(eme_sanitize_request($_POST['bookedSeats']));

   $event = eme_get_event($event_id);
   $registration_wp_users_only=$event['registration_wp_users_only'];
   if ($registration_wp_users_only) {
      // we require a user to be WP registered to be able to book
      if (!is_user_logged_in()) {
         return;
      }
      $readonly="disabled='disabled'";
   } else {
      $readonly="";
   }
   #$destination = eme_event_url($event)."#eme-rsvp-message";
   $destination = "#eme-rsvp-message";

   $event_start_datetime = strtotime($event['event_start_date']." ".$event['event_start_time']);
   if (time()+$event['rsvp_number_days']*60*60*24+$event['rsvp_number_hours']*60*60 > $event_start_datetime ) {
      $ret_string = "<div id='eme-rsvp-message'>";
      if(!empty($form_add_message))
         $ret_string .= "<div class='eme-rsvp-message'>$form_add_message</div>";
      if(!empty($form_error_message))
         $ret_string .= "<div class='eme-rsvp-message'>$form_error_message</div>";
      return $ret_string."<div class='eme-rsvp-message'>".__('Bookings no longer allowed on this date.', 'eme')."</div></div>";
   }

   # you did a successfull registration, so now we decide wether to show the form again, or the paypal form
   if(!empty($form_add_message) && empty($form_error_message)) {
      $ret_string = "<div class='eme-rsvp-message'>$form_add_message</div>";
      $ret_string .= eme_payment_form($event,$booking_id_done);
      return $ret_string;
   }

   // you can book the available number of seats, with a max of x per time
   if (eme_is_multiprice($event['price']))
      $min_allowed = 0;
   else
      $min_allowed = intval(get_option('eme_rsvp_addbooking_min_spaces'));
   $max_allowed = intval(get_option('eme_rsvp_addbooking_max_spaces'));
   $max = eme_get_available_seats($event_id);
   if ($max > $max_allowed && $max_allowed>0) {
      $max = $max_allowed;
   }
   // just for stupidity reasons
   if ($min_allowed<0) $min_allowed=0;
   // no seats anymore? No booking form then ... but only if it is required that the min number of
   // bookings should be >0 (it can be=0 for attendance bookings)
   if ($max == 0 && $min_allowed>0) {
      $ret_string = "<div id='eme-rsvp-message'>";
      if(!empty($form_add_message))
         $ret_string .= "<div class='eme-rsvp-message'>$form_add_message</div>";
      if(!empty($form_error_message))
         $ret_string .= "<div class='eme-rsvp-message'>$form_error_message</div>";
      return $ret_string."<div class='eme-rsvp-message'>".__('Bookings no longer possible: no seats available anymore', 'eme')."</div></div>";
   }

   $form_html="";
   if(!empty($form_add_message))
      $form_html .= "<div class='eme-rsvp-message'>$form_add_message</div>";
   if(!empty($form_error_message))
      $form_html .= "<div class='eme-rsvp-message'>$form_error_message</div>";
   # only add the id to the div if it is not empty
   if(!empty($form_html))
      $form_html = "<div id='eme-rsvp-message'>".$form_html."</div>";

   $booked_places_options = array();
   for ( $i = $min_allowed; $i <= $max; $i++) 
      $booked_places_options[$i]=$i;
   
   $form_html .= "<form id='eme-rsvp-form' name='booking-form' method='post' action='$destination'>";
   $form_html .= eme_replace_formfields_placeholders ($event, $readonly, $bookedSeats, $booked_places_options, $bookerName, $bookerEmail, $bookerPhone, $bookerComment);
   // also add a honeypot field: if it gets completed with data, 
   // it's a bot, since a humand can't see this (using CSS to render it invisible)
   $form_html .= "<span id='honeypot_check'>Keep this field blank: <input type='text' name='honeypot_check' value='' /></span>
      <p>".__('(* marks a required field)', 'eme')."</p>
      <input type='hidden' name='eme_eventAction' value='add_booking'/>
      <input type='hidden' name='event_id' value='$event_id'/>
   </form>";
 
   if (has_filter('eme_add_booking_form_filter')) $form_html=apply_filters('eme_add_booking_form_filter',$form_html);
   return $form_html;
   
}

function eme_add_booking_form_shortcode($atts) {
   extract ( shortcode_atts ( array ('id' => 0), $atts));
   echo eme_add_booking_form($id);
}
add_shortcode ('events_add_booking_form','eme_add_booking_form_shortcode');

function eme_delete_booking_form($event_id) {
   global $form_delete_message, $current_user;
   
   if (is_user_logged_in()) {
      get_currentuserinfo();
      $bookerName=$current_user->display_name;
      $bookerEmail=$current_user->user_email;
   } else {
      $bookerName="";
      $bookerEmail="";
   }
   $form_html = "";
   $event = eme_get_event($event_id);
   $registration_wp_users_only=$event['registration_wp_users_only'];
   if ($registration_wp_users_only) {
      // we require a user to be WP registered to be able to book
      if (!is_user_logged_in()) {
         return;
      }
      $readonly="disabled='disabled'";
   } else {
      $readonly="";
   }
   #$destination = eme_event_url($event)."#eme-rsvp-message";
   $destination = "#eme-rsvp-message";
   
   $event_start_datetime = strtotime($event['event_start_date']." ".$event['event_start_time']);
   if (time()+$event['rsvp_number_days']*60*60*24+$event['rsvp_number_hours']*60*60 > $event_start_datetime ) {
      $ret_string = "<div id='eme-rsvp-message'>";
      if(!empty($form_delete_message))
         $ret_string .= "<div class='eme-rsvp-message'>$form_delete_message</div>";
      return $ret_string."<div class='eme-rsvp-message'>".__('Bookings no longer allowed on this date.', 'eme')."</div></div>";
   }

   if(!empty($form_delete_message)) {
      $form_html = "<div id='eme-rsvp-message'>";
      $form_html .= "<div class='eme-rsvp-message'>$form_delete_message</div>";
      $form_html .= "</div>";
   }

   $form_html  .= "<form id='booking-delete-form' name='booking-delete-form' method='post' action='$destination'>
      <table class='eme-rsvp-form'>
         <tr><th scope='row'>".__('Name', 'eme').":</th><td><input type='text' name='bookerName' value='$bookerName' $readonly /></td></tr>
         <tr><th scope='row'>".__('E-Mail', 'eme').":</th><td><input type='text' name='bookerEmail' value='$bookerEmail' $readonly /></td></tr>
      </table>
      <input type='hidden' name='eme_eventAction' value='delete_booking'/>
      <input type='hidden' name='event_id' value='$event_id'/>
      <input type='submit' value='".eme_translate(get_option('eme_rsvp_delbooking_submit_string'))."'/>
   </form>";

   if (has_filter('eme_delete_booking_form_filter')) $form_html=apply_filters('eme_delete_booking_form_filter',$form_html);
   return $form_html;
}

function eme_delete_booking_form_shortcode($atts) {
   extract ( shortcode_atts ( array ('id' => 0), $atts));
   echo eme_delete_booking_form($id);
}
add_shortcode ('events_delete_booking_form','eme_delete_booking_form_shortcode');

function eme_catch_rsvp() {
   global $current_user;
   global $form_add_message;
   global $form_error_message;
   global $form_delete_message; 
   global $booking_id_done;
   $result = "";

   if (isset($_GET['eme_eventAction']) && ($_GET['eme_eventAction']=="paypal_notification" || $_GET['eme_eventAction']=="paypal_ipn")) {
      return eme_paypal_notification();
   }
   if (isset($_GET['eme_eventAction']) && ($_GET['eme_eventAction']=="2co_notification" || $_GET['eme_eventAction']=="2co_ins")) {
      return eme_2co_notification();
   }
   if (isset($_GET['eme_eventAction']) && $_GET['eme_eventAction']=="webmoney_notification") {
      return eme_webmoney_notification();
   }
   // make sure we don't get too far without proper info
   if (!(isset($_POST['eme_eventAction']) && isset($_POST['event_id']))) {
      return;
   }

   if (get_option('eme_captcha_for_booking')) {
      // the captcha needs a session
      if (!session_id())
         session_start();
   }

   $event_id = intval($_POST['event_id']);
   $event = eme_get_event($event_id);
   $registration_wp_users_only=$event['registration_wp_users_only'];
   if ($registration_wp_users_only && !is_user_logged_in()) {
      return;
   }

   if (isset($_POST['eme_eventAction']) && $_POST['eme_eventAction'] == 'add_booking') { 
      $booking_res = eme_book_seats($event);
      $result=$booking_res[0];
      $booking_id_done=$booking_res[1];
      // no booking? then fill in global error var
      if (!$booking_id_done)
         $form_error_message = $result;
      else
         $form_add_message = $result;
   } 

   if (isset($_POST['eme_eventAction']) && $_POST['eme_eventAction'] == 'delete_booking') { 
      $result = eme_cancel_seats($event);
      $form_delete_message = $result; 
   } 
   return $result;
}
add_action('init','eme_catch_rsvp');
 
function eme_cancel_seats($event) {
   global $current_user;
   $event_id = $event['event_id'];
   $registration_wp_users_only=$event['registration_wp_users_only'];
   if ($registration_wp_users_only) {
      // we require a user to be WP registered to be able to book
      get_currentuserinfo();
      $booker_wp_id=$current_user->ID;
      // we also need name and email for sending the mail
      $bookerName = $current_user->display_name;
      $bookerEmail = $current_user->user_email;
      $booker = eme_get_person_by_wp_id($booker_wp_id); 
   } else {
      $bookerName = eme_strip_tags($_POST['bookerName']);
      $bookerEmail = eme_strip_tags($_POST['bookerEmail']);
      $booker = eme_get_person_by_name_and_email($bookerName, $bookerEmail); 
   }
   if ($booker) {
      $person_id = $booker['person_id'];
      $booking_ids=eme_get_booking_ids_by_person_event_id($person_id,$event_id);
      if (!empty($booking_ids)) {
         foreach ($booking_ids as $booking_id) {
            eme_email_rsvp_booking($booking_id,"cancelRegistration");
            eme_delete_booking($booking_id);
         }
         $result = __('Booking deleted', 'eme');
      } else {
         $result = __('There are no bookings associated to this name and e-mail', 'eme');
      }
   } else {
      $result = __('There are no bookings associated to this name and e-mail', 'eme');
   }
   return $result;
}

// the eme_book_seats can also be called from the admin backend, that's why for certain things, we check using is_admin where we are
function eme_book_seats($event, $send_mail=1) {
   global $current_user;
   $booking_id = 0;
   $all_required_fields_ok=1;
   $all_required_fields=eme_find_required_formfields($event['event_registration_form_format']);
   $min_allowed = intval(get_option('eme_rsvp_addbooking_min_spaces'));
   $max_allowed = intval(get_option('eme_rsvp_addbooking_max_spaces'));

   if (isset($_POST['bookedSeats']))
      $bookedSeats = intval($_POST['bookedSeats']);
   else
      $bookedSeats = 0;

   // for multiple prices, we have multiple booked Seats as well
   // the next foreach is only valid when called from the frontend
   $bookedSeats_mp = array();
   if (!is_admin()) {
	   foreach($_POST as $key=>$value) {
		   if (preg_match('/bookedSeats(\d+)/', $key, $matches)) {
			   $field_id = intval($matches[1]);
			   $bookedSeats += $value;
			   $bookedSeats_mp[$field_id]=$value;
		   }
	   }
   } else {
      if (eme_is_multiprice($event['price'])) {
         if (isset($_POST['bookedSeats_mp'])) {
            $bookedSeats_mp = preg_split("/\|\|/",$_POST['bookedSeats_mp']);
            $booking_prices = preg_split("/\|\|/",$event['price']);
            $count1=count($booking_prices);
            $count2=count($bookedSeats_mp);
            if ($count1 != $count2) {
               $result = sprintf(__("'%s' is a multiprice event, please fill in %d sets of spaces to reserve in the '%s' field.",'eme'),$event['event_name'],$count1,__('Seats (Multiprice)', 'eme'));
               $res = array(0=>$result,1=>$booking_id);
               return $res;
            } else {
               foreach($bookedSeats_mp as $value) {
                  $bookedSeats += $value;
               }
            }
         } else {
            $result = sprintf(__("'%s' is a multiprice event, please fill in %d sets of spaces to reserve in the '%s' field.",'eme'),$event['event_name'],$count1,__('Seats (Multiprice)', 'eme'));
            $res = array(0=>$result,1=>$booking_id);
            return $res;
         }
      }
   }

   if (isset($_POST['bookerPhone']))
      $bookerPhone = eme_strip_tags($_POST['bookerPhone']); 
   else
      $bookerPhone = "";

   if (isset($_POST['bookerComment']))
      $bookerComment = eme_strip_tags($_POST['bookerComment']);
   else
      $bookerComment = "";

   if (isset($_POST['honeypot_check']))
      $honeypot_check = stripslashes($_POST['honeypot_check']);
   else
      $honeypot_check = "";

   // check all required fields
   if (!is_admin()) {
      foreach ($all_required_fields as $required_field) {
         if (preg_match ("/NAME|EMAIL|SEATS/",$required_field)) {
            // we already check these seperately
            next;
         } elseif (strcmp($required_field,"PHONE")==0) {
            if (empty($bookerPhone)) $all_required_fields_ok=0;
         } elseif (strcmp($required_field,"COMMENT")==0) {
            if (empty($bookerComment)) $all_required_fields_ok=0;
         } elseif (!isset($_POST[$required_field]) || empty($_POST[$required_field])) {
            $all_required_fields_ok=0;
         }
      }
   }

   $event_id = $event['event_id'];
   $registration_wp_users_only=$event['registration_wp_users_only'];
   // if we're booking via the admin backend, we don't care about registration_wp_users_only
   if (!is_admin() && $registration_wp_users_only) {
      // we require a user to be WP registered to be able to book
      get_currentuserinfo();
      $booker_wp_id=$current_user->ID;
      // we also need name and email for sending the mail
      $bookerName = $current_user->display_name;
      $bookerEmail = $current_user->user_email;
      $booker = eme_get_person_by_wp_id($booker_wp_id); 
   } else {
      $booker_wp_id=0;
      $bookerName = eme_strip_tags($_POST['bookerName']);
      $bookerEmail = eme_strip_tags($_POST['bookerEmail']);
      $booker = eme_get_person_by_name_and_email($bookerName, $bookerEmail); 
   }
   
   if (!is_admin() && get_option('eme_captcha_for_booking')) {
      $captcha_err = response_check_captcha("captcha_check",1);
   } else {
      $captcha_err = "";
   }
   if(!empty($captcha_err)) {
      $result = __('You entered an incorrect code','eme');
   } elseif ($honeypot_check != "") {
      // a bot fills this in, but a human never will, since it's
      // a hidden field
      $result = __('You are a bad boy','eme');
   } elseif (!$bookerName || !$bookerEmail || !$all_required_fields_ok) {
      // if any required field is empty: return an error
      $result = __('Please fill in all the required fields','eme');
   } elseif (!filter_var($bookerEmail,FILTER_VALIDATE_EMAIL)) {
      $result = __('Please enter a valid mail address','eme');
   } elseif ($bookedSeats < $min_allowed) {
      $result = __('Please fill in a correct number of spaces to reserve','eme');
   } elseif ($max_allowed>0 && $bookedSeats>$max_allowed) {
      $result = __('Please fill in a correct number of spaces to reserve','eme');
   } elseif (!is_admin() && $registration_wp_users_only && !$booker_wp_id) {
      // spammers might get here, but we catch them
      $result = __('WP membership is required for registration','eme');
   } else {
      if (eme_are_seats_available_for($event_id, $bookedSeats)) {
         if (!$booker) {
            $booker = eme_add_person($bookerName, $bookerEmail, $bookerPhone, $booker_wp_id);
         }

         // ok, just to be safe: check the person_id of the booker
         if ($booker['person_id']>0) {
            // if the user enters a new phone number, update it
            if ($booker['person_phone'] != $bookerPhone) {
               eme_update_phone($booker,$bookerPhone);
            }

            $booking_id=eme_record_booking($event, $booker['person_id'], $bookedSeats,$bookedSeats_mp,$bookerComment);
            eme_record_answers($booking_id);
            $format = ( $event['event_registration_recorded_ok_html'] != '' ) ? $event['event_registration_recorded_ok_html'] : get_option('eme_registration_recorded_ok_html' );
            $result = eme_replace_placeholders($format, $event);
            if (is_admin()) {
               $action="approveRegistration";
            } else {
               $action="";
            }
            if ($send_mail) eme_email_rsvp_booking($booking_id,$action);

            // everything ok, so we unset the variables entered, so when the form is shown again, all is defaulted again
            foreach($_POST as $key=>$value) {
               unset($_POST[$key]);
            }
         } else {
            $result = __('No booker ID found, something is wrong here','eme');
            unset($_POST['bookedSeats']);
         }
      } else {
         $result = __('Booking cannot be made: not enough seats available!', 'eme');
         // here we only unset the number of seats entered, so the user doesn't have to fill in the rest again
         unset($_POST['bookedSeats']);
      }
   }

   $res = array(0=>$result,1=>$booking_id);
   return $res;
}

function eme_get_booking($booking_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = "SELECT * FROM $bookings_table WHERE booking_id = '$booking_id';" ;
   $result = $wpdb->get_row($sql, ARRAY_A);
   // for older bookings, the booking_price field might be empty
   if ($result['booking_price']==="")
      $result['booking_price'] = eme_get_event_price($result['event_id']);
   return $result;
}

function eme_get_event_price($event_id) {
   global $wpdb; 
   $events_table = $wpdb->prefix.EVENTS_TBNAME;
   $sql = $wpdb->prepare("SELECT price FROM $events_table WHERE event_id =%d",$event_id);
   $result = $wpdb->get_var($sql);
   return $result;
   }

function eme_get_bookings_by_person_id($person_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT * FROM $bookings_table WHERE person_id = %d",$person_id);
   $result = $wpdb->get_results($sql, ARRAY_A);
   return $result;
}

function eme_get_booking_by_person_event_id($person_id,$event_id) {
   return eme_get_booking_ids_by_person_event_id($person_id,$event_id);
}
function eme_get_booking_ids_by_person_event_id($person_id,$event_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $sql = $wpdb->prepare("SELECT booking_id FROM $bookings_table WHERE person_id = %d AND event_id = %d",$person_id,$event_id);
   $result = $wpdb->get_col($sql);
   return $result;
}

function eme_get_booked_seats_by_person_event_id($person_id,$event_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT COALESCE(SUM(booking_seats),0) AS booked_seats FROM $bookings_table WHERE person_id = %d AND event_id = %d",$person_id,$event_id);
   return $wpdb->get_var($sql);
}

function eme_get_event_id_by_booking_id($booking_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT DISTINCT event_id FROM $bookings_table WHERE booking_id = %d",$booking_id);
   $result = $wpdb->get_var($sql);
   return $result;
}

function eme_get_event_ids_by_booker_id($person_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT DISTINCT event_id FROM $bookings_table WHERE person_id = %d",$person_id);
   $result = $wpdb->get_col($sql);
   return $result;
}

function eme_record_booking($event, $person_id, $seats, $seats_mp, $comment = "") {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $person_id = intval($person_id);
   $seats = intval($seats);
   $comment = eme_sanitize_request($comment);
   $booking['event_id']=$event['event_id'];
   $booking['person_id']=$person_id;
   $booking['booking_seats']=$seats;
   $booking['booking_seats_mp']=join("||",$seats_mp);
   $booking['booking_price']=$event['price'];
   $booking['booking_comment']=$comment;
   $booking['creation_date']=current_time('mysql', false);
   $booking['modif_date']=current_time('mysql', false);
   $booking['creation_date_gmt']=current_time('mysql', true);
   $booking['modif_date_gmt']=current_time('mysql', true);
   // only if we're not adding a booking in the admin backend, check for approval needed
   if (!is_admin() && $event['registration_requires_approval']) {
      $booking['booking_approved']=0;
   } else {
      $booking['booking_approved']=1;
   }

   // checking whether the booker has already booked places
// $sql = "SELECT * FROM $bookings_table WHERE event_id = '$event_id' and person_id = '$person_id'; ";
// //echo $sql;
// $previously_booked = $wpdb->get_row($sql);
// if ($previously_booked) {
//    $total_booked_seats = $previously_booked->booking_seats + $seats;
//    $where = array();
//    $where['booking_id'] =$previously_booked->booking_id;
//    $fields['booking_seats'] = $total_booked_seats;
//    $wpdb->update($bookings_table, $fields, $where);
// } else {
      //$sql = "INSERT INTO $bookings_table (event_id, person_id, booking_seats,booking_comment) VALUES ($event_id, $person_id, $seats,'$comment')";
      //$wpdb->query($sql);
      if ($wpdb->insert($bookings_table,$booking)) {
         $booking['booking_id'] = $wpdb->insert_id;
         $transfer_nbr_be97_main=sprintf("%010d",$booking['booking_id']);
         // the control number is the %97 result, or 97 in case %97=0
         $transfer_nbr_be97_check=$transfer_nbr_be97_main % 97;
	if ($transfer_nbr_be97_check==0)
		$transfer_nbr_be97_check = 97 ;
         $transfer_nbr_be97_check=sprintf("%02d",$transfer_nbr_be97_check);
         $transfer_nbr_be97 = $transfer_nbr_be97_main.$transfer_nbr_be97_check;
         $transfer_nbr_be97 = substr($transfer_nbr_be97,0,3)."/".substr($transfer_nbr_be97,3,4)."/".substr($transfer_nbr_be97,7,5);
         $booking['transfer_nbr_be97'] = $transfer_nbr_be97_main.$transfer_nbr_be97_check;
         $where = array();
         $fields = array();
         $where['booking_id'] = $booking['booking_id'];
         $fields['transfer_nbr_be97'] = $booking['transfer_nbr_be97'];
         $wpdb->update($bookings_table, $fields, $where);
         if (has_action('eme_insert_rsvp_action')) do_action('eme_insert_rsvp_action',$booking);
         return $booking['booking_id'];
      } else {
         return false;
      }
// }
}

function eme_record_answers($booking_id) {
   global $wpdb;
   $answers_table = $wpdb->prefix.ANSWERS_TBNAME; 
   foreach($_POST as $key =>$value) {
      if (preg_match('/FIELD(.+)/', $key, $matches)) {
         $field_id = intval($matches[1]);
         $formfield = eme_get_formfield_byid($field_id);
         $sql = $wpdb->prepare("INSERT INTO $answers_table (booking_id,field_name,answer) VALUES (%d,%s,%s)",$booking_id,$formfield['field_name'],stripslashes($value));
         $wpdb->query($sql);
      }
   }
}

function eme_get_answers($booking_id) {
   global $wpdb;
   $answers_table = $wpdb->prefix.ANSWERS_TBNAME; 
   $sql = $wpdb->prepare("SELECT * FROM $answers_table WHERE booking_id=%d",$booking_id);
   return $wpdb->get_results($sql, ARRAY_A);
}

function eme_delete_answers($booking_id) {
   global $wpdb;
   $answers_table = $wpdb->prefix.ANSWERS_TBNAME; 
   $sql = $wpdb->prepare("DELETE FROM $answers_table WHERE booking_id=%d",$booking_id);
   $wpdb->query($sql);
}

function eme_get_answercolumns($booking_ids) {
   global $wpdb;
   $answers_table = $wpdb->prefix.ANSWERS_TBNAME; 
   $sql = "SELECT DISTINCT field_name FROM $answers_table WHERE booking_id IN (".join(",",$booking_ids).")";
   return $wpdb->get_results($sql, ARRAY_A);
}

function eme_delete_all_bookings_for_person_id($person_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $sql = "DELETE FROM $bookings_table WHERE person_id = $person_id";
   $wpdb->query($sql);
   #$person = eme_get_person($person_id);
   return 1;
}
function eme_delete_booking_by_person_event_id($person_id,$event_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $sql = $wpdb->prepare("DELETE FROM $bookings_table WHERE person_id = %d AND event_id= %d",$person_id,$event_id);
   return $wpdb->query($sql);
}
function eme_delete_booking($booking_id) {
   global $wpdb;
   // first delete all the answers
   eme_delete_answers($booking_id);
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $sql = $wpdb->prepare("DELETE FROM $bookings_table WHERE booking_id = %d",$booking_id);
   return $wpdb->query($sql);
}
function eme_update_booking_payed($booking_id,$value) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $sql = $wpdb->prepare("UPDATE $bookings_table set booking_payed=%d  WHERE booking_id = %d",$value,$booking_id);
   return $wpdb->query($sql);
}
function eme_approve_booking($booking_id) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 

   $where = array();
   $fields = array();
   $where['booking_id'] =$booking_id;
   $fields['booking_approved'] = 1;
   $fields['modif_date']=current_time('mysql', false);
   $fields['modif_date_gmt']=current_time('mysql', true);
   $wpdb->update($bookings_table, $fields, $where);
   //$sql = "UPDATE $bookings_table SET booking_approved='1' WHERE booking_id = $booking_id";
   //$wpdb->query($sql);
   return __('Booking approved', 'eme');
}
function eme_update_booking_seats($booking_id,$event_id,$seats,$booking_price) {
   global $wpdb;
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME; 
   $where = array();
   $fields = array();
   $where['booking_id'] =$booking_id;

   # if it is a multi-price event, the total number of seats is the sum of the other ones
   if (eme_is_multiprice($booking_price)) {
      $fields['booking_seats']=0;
      # make sure the correct amount of seats is defined for multiprice
      $booking_prices_mp=preg_split("/\|\|/",$booking_price);
      $booking_seats_mp=preg_split("/\|\|/",$seats);
      foreach ($booking_prices_mp as $key=>$value) {
         if (!isset($booking_seats_mp[$key]))
            $booking_seats_mp[$key] = 0;
         $fields['booking_seats'] += intval($booking_seats_mp[$key]);
      }
      $fields['booking_seats_mp'] = join("||",$booking_seats_mp);
   } else {
      $fields['booking_seats'] = intval($seats);
   }
   $fields['modif_date']=current_time('mysql', false);
   $fields['modif_date_gmt']=current_time('mysql', true);
   $wpdb->update($bookings_table, $fields, $where);
   //$sql = "UPDATE $bookings_table SET booking_seats='$seats' WHERE booking_id = $booking_id";
   //$wpdb->query($sql);
   return __('Booking approved', 'eme');
}

function eme_get_available_seats($event_id) {
   $event = eme_get_event($event_id);
   $available_seats = $event['event_seats'] - eme_get_booked_seats($event_id);
   // the number of seats left can be <0 if more than one booking happened at the same time and people fill in things slowly
   if ($available_seats<0) $available_seats=0;
   return $available_seats;
}

function eme_get_booked_seats($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = "SELECT COALESCE(SUM(booking_seats),0) AS booked_seats FROM $bookings_table WHERE event_id = $event_id"; 
   return $wpdb->get_var($sql);
}

function eme_get_approved_seats($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = "SELECT COALESCE(SUM(booking_seats),0) AS booked_seats FROM $bookings_table WHERE event_id = $event_id and booking_approved=1"; 
   return $wpdb->get_var($sql);
}

function eme_get_pending_seats($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = "SELECT COALESCE(SUM(booking_seats),0) AS booked_seats FROM $bookings_table WHERE event_id = $event_id and booking_approved=0"; 
   return $wpdb->get_var($sql);
}

function eme_get_pending_bookings($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = "SELECT COUNT(*) AS pending_bookings FROM $bookings_table WHERE event_id = $event_id and booking_approved=0"; 
   return $wpdb->get_var($sql);
}

function eme_are_seats_available_for($event_id, $seats) {
   #$event = eme_get_event($event_id);
   $available_seats = eme_get_available_seats($event_id);
   $remaning_seats = $available_seats - $seats;
   return ($remaning_seats >= 0);
} 
 
function eme_bookings_table($event_id) {
   $bookings =  eme_get_bookings_for($event_id);
   $event =  eme_get_event($event_id);
   $destination = admin_url("edit.php"); 
   $result = "<form id='bookings-filter' name='bookings-filter' method='get' action='$destination'>
                  <input type='hidden' name='page' value='eme_registration_seats_page'/>
                  <input type='hidden' name='event_id' value='$event_id'/>
                  <input type='hidden' name='action' value='delete_bookings'/>
                  <div class='wrap'>
                     <h2>Bookings</h2>
                  <table id='eme-bookings-table' class='widefat post fixed'>";
   $result .="<thead>
                     <tr><th class='manage-column column-cb check-column' scope='col'>&nbsp;</th><th class='manage-column ' scope='col'>".__('Booker','eme')."</th><th scope='col'>".__('E-mail','eme')."</th><th scope='col'>".__('Phone number','eme')."</th><th scope='col'>".__('Seats','eme')."</th><th scope='col'>".__('Unique nbr','eme')."</th></tr>
                  </thead>" ;
   foreach ($bookings as $booking) {
      $person  = eme_get_person ($booking['person_id']);
      $result .= "<tr> <td><input type='checkbox' value='".$booking['booking_id']."' name='bookings[]'/></td>
                              <td>".eme_sanitize_html($person['person_name'])."</td>
                              <td>".eme_sanitize_html($person['person_email'])."</td>
                              <td>".eme_sanitize_html($person['person_phone'])."</td>
                 ";
      if (eme_is_multiprice(eme_get_booking_price($event,$booking))) {
         $result .= "<td>".$booking['booking_seats_mp']."</td>";
      } else {
         $result .= "<td>".$booking['booking_seats']."</td>";
      }
      $result .= "<td>".eme_sanitize_html($booking['transfer_nbr_be97'])."</td>
                  </tr>";
   }
   $available_seats = eme_get_available_seats($event_id);
   $booked_seats = eme_get_booked_seats($event_id);
   $result .= "<tfoot><tr><th scope='row' colspan='4'>".__('Booked spaces','eme').":</th><td class='booking-result' id='booked-seats'>$booked_seats</td></tr>
                   <tr><th scope='row' colspan='4'>".__('Available spaces','eme').":</th><td class='booking-result' id='available-seats'>$available_seats</td></tr></tfoot>
                     </table></div>
                     <div class='tablenav'>
                        <div class='alignleft actions'>
                         <input class=button-secondary action' type='submit' name='doaction' value='Delete'/>
                           <br class='clear'/>
                        </div>
                        <br class='clear'/>
                     </div>
                  </form>";
   echo $result;
}

function eme_bookings_compact_table($event_id) {
   $bookings =  eme_get_bookings_for($event_id);
   $destination = admin_url("edit.php"); 
   $available_seats = eme_get_available_seats($event_id);
   $approved_seats = eme_get_approved_seats($event_id);
   $pending_seats = eme_get_pending_seats($event_id);
   $booked_seats = eme_get_booked_seats($event_id);
   if ($pending_seats>0) {
      $booked_seats_info="$booked_seats ($approved_seats ".__('approved','eme').", $pending_seats ".__('pending','eme');
   } else {
      $booked_seats_info=$booked_seats;
   }
   $printable_address = admin_url("/admin.php?page=eme-people&amp;action=booking_printable&amp;event_id=$event_id");
   $csv_address = admin_url("/admin.php?page=eme-people&amp;action=booking_csv&amp;event_id=$event_id");
   $count_respondents=count($bookings);
   if ($count_respondents>0) { 
      $table = 
      "<div class='wrap'>
            <h4>$count_respondents ".__('respondents so far').":</h4>
            <table id='eme-bookings-table-$event_id' class='widefat post fixed'>
               <thead>
                  <tr>
                     <th class='manage-column column-cb check-column' scope='col'>&nbsp;</th>
                     <th class='manage-column ' scope='col'>".__('Respondent', 'eme')."</th>
                     <th scope='col'>".__('Spaces', 'eme')."</th>
                  </tr>
               </thead>
               <tfoot>
                  <tr>
                     <th scope='row' colspan='2'>".__('Booked spaces','eme').":</th><td class='booking-result' id='booked-seats'>$booked_seats_info</td></tr>
                  <tr><th scope='row' colspan='2'>".__('Available spaces','eme').":</th><td class='booking-result' id='available-seats'>$available_seats</td>
                  </tr>
               </tfoot>
               <tbody>" ;
      foreach ($bookings as $booking) {
         $person  = eme_get_person ($booking['person_id']);
         ($booking['booking_comment']) ? $baloon = " <img src='".EME_PLUGIN_URL."images/baloon.png' title='".__('Comment:','eme')." ".$booking['booking_comment']."' alt='comment'/>" : $baloon = "";
         $pending_string="";
         if (eme_event_needs_approval($event_id) && !$booking['booking_approved']) {
            $pending_string=__('(pending)','eme');
         }
         $table .= 
         "<tr id='booking-".$booking['booking_id']."'> 
            <td><a id='booking-check-".$booking['booking_id']."' class='bookingdelbutton'>X</a></td>
            <td><a title=\"".eme_sanitize_html($person['person_email'])." - ".eme_sanitize_html($person['person_phone'])."\">".eme_sanitize_html($person['person_name'])."</a>$baloon</td>
            <td>".$booking['booking_seats']." $pending_string </td>
          </tr>";
      }
    
      $table .=  "</tbody>
         </table>
         </div>
         <br class='clear'/>
         <div id='major-publishing-actions'>
         <div id='publishing-action'> 
            <a id='printable'  target='' href='$printable_address'>".__('Printable view','eme')."</a>
            <br class='clear'/>
         </div>
         <div id='publishing-action-csv'> 
            <a id='printable'  target='' href='$csv_address'>".__('CSV export','eme')."</a>
            <br class='clear'/>
         </div>
         <br class='clear'/>
         </div> ";
   } else {
      $table = "<p><em>".__('No responses yet!','eme')."</em></p>";
   } 
   echo $table;
}

function eme_get_bookingids_for($event_id) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT booking_id FROM $bookings_table WHERE event_id=%d",$event_id);
   return $wpdb->get_col($sql);
}

function eme_get_bookings_for($event_ids,$pending_approved=0,$only_unpayed=0) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   
   $bookings = array();
   if (!$event_ids)
      return $bookings;
   
   if (is_array($event_ids)) {
      $where="event_id IN (".join(",",$event_ids).")";
   } else {
      $where="event_id = $event_ids";
   }
   $sql = "SELECT * FROM $bookings_table WHERE $where";
   if ($pending_approved==1) {
      $sql .= " AND booking_approved=0";
   } elseif ($pending_approved==2) {
      $sql .= " AND booking_approved=1";
   }
   if ($only_unpayed) {
      $sql .= " AND booking_payed=0";
   }
   return $wpdb->get_results($sql, ARRAY_A);
}

function eme_get_attendees_for($event_id,$pending_approved=0,$only_unpayed=0) {
   global $wpdb; 
   $bookings_table = $wpdb->prefix.BOOKINGS_TBNAME;
   $sql = $wpdb->prepare("SELECT DISTINCT person_id FROM $bookings_table WHERE event_id = %s",$event_id);
   if ($pending_approved==1) {
      $sql .= " AND booking_approved=0";
   } elseif ($pending_approved==2) {
      $sql .= " AND booking_approved=1";
   }
   if ($only_unpayed) {
      $sql .= " AND booking_payed=0";
   }

   $person_ids = $wpdb->get_col($sql);
   if ($person_ids) {
      $attendees = eme_get_persons($person_ids);
   } else {
      $attendees= array();
   }
   return $attendees;
}

function eme_get_attendees_list_for($event_id) {
   $attendees = eme_get_attendees_for($event_id);
   if ($attendees) {
      $res="<ul class='eme_bookings_list_ul'>";
      foreach ($attendees as $attendee) {
         $res.=eme_replace_attendees_placeholders(get_option('eme_attendees_list_format'),$attendee,$event_id);
      }
      $res.="</ul>";
   } else {
      $res="<p class='eme_no_bookings'>".__('No responses yet!','eme')."</p>";
   }
   return $res;
}

function eme_get_bookings_list_for($event_id) {
   global $wpdb; 
   $bookings=eme_get_bookings_for($event_id);
   if ($bookings) {
      $res=get_option('eme_bookings_list_header_format');
      foreach ($bookings as $booking) {
         $res.= eme_replace_booking_placeholders(get_option('eme_bookings_list_format'),$booking);
      }
      $res.=get_option('eme_bookings_list_footer_format');
   } else {
      $res="<p class='eme_no_bookings'>".__('No responses yet!','eme')."</p>";
   }
   return $res;
}

function eme_replace_booking_placeholders($format, $booking, $target="html") {
   preg_match_all("/#(ESC)?_?[A-Za-z0-9_]+/", $format, $placeholders);
   $person  = eme_get_person ($booking['person_id']);
   $answers = eme_get_answers($booking['booking_id']);
   foreach($placeholders[0] as $result) {
      $replacement='';
      $found = 1;
      $need_escape=0;
      $orig_result = $result;
      if (strstr($result,'#ESC')) {
         $result = str_replace("#ESC","#",$result);
         $need_escape=1;
      }
      if (preg_match('/#_RESP(NAME|PHONE|ID|EMAIL)$/', $result)) {
         $field = preg_replace("/#_RESP/","",$result);
         $field = "person_".strtolower($field);
         $replacement = $person[$field];
         $replacement = eme_sanitize_html($replacement);
         if ($target == "html")
            $replacement = apply_filters('eme_general', $replacement); 
         else 
            $replacement = apply_filters('eme_general_rss', $replacement); 
      } elseif (preg_match('/#_(RESPCOMMENT|COMMENT)$/', $result)) {
         $replacement = $booking['booking_comment'];
         $replacement = eme_sanitize_html($replacement);
         if ($target == "html")
            $replacement = apply_filters('eme_general', $replacement); 
         else 
            $replacement = apply_filters('eme_general_rss', $replacement); 
      } elseif (preg_match('/#_RESPSPACES(.+)/', $result, $matches)) {
         $field_id = intval($matches[1])-1;
         if ($field_id<0) $field_id=0;
         if (eme_is_multiprice($booking['booking_price'])) {
             $seats=preg_split("/\|\|/",$booking['booking_seats_mp']);
             if (array_key_exists($field_id,$seats))
                $replacement = $seats[$field_id];
         }
      } elseif (preg_match('/#_RESPSPACES$/', $result)) {
         $replacement = $booking['booking_seats'];
      } elseif (preg_match('/#_USER_(RESERVEDSPACES|BOOKEDSEATS)$/', $result)) {
         $replacement = $booking['booking_seats'];
      } elseif (preg_match('/#_(SPACES|BOOKEDSEATS)$/', $result)) {
         $replacement = $booking['booking_seats'];
      } elseif (preg_match('/#_BOOKINGCREATIONDATE$/', $result)) {
         $replacement = eme_localised_date($booking['creation_date']);
      } elseif (preg_match('/#_BOOKINGMODIFDATE$/', $result)) {
         $replacement = eme_localised_date($booking['modif_date']);
      } elseif (preg_match('/#_BOOKINGCREATIONTIME$/', $result)) {
         $replacement = eme_localised_time($booking['creation_date']);
      } elseif (preg_match('/#_BOOKINGMODIFTIME$/', $result)) {
         $replacement = eme_localised_time($booking['modif_date']);
      } elseif (preg_match('/#_TRANSFER_NBR_BE97$/', $result)) {
         $replacement = $booking['transfer_nbr_be97'];
      } elseif (preg_match('/#_PAYMENT_URL$/', $result)) {
         $replacement = eme_payment_url($booking['booking_id']);
      } elseif (preg_match('/#_FIELDS$/', $result)) {
         $field_replace = "";
         foreach ($answers as $answer) {
            $field_replace.=$answer['field_name'].": ".$answer['answer']."\n";
         }
         $replacement = $field_replace;
      } elseif (preg_match('/#_PAYED/', $result, $matches)) {
         $replacement = ($booking['booking_payed'])? __('Yes') : __('No');
      } elseif (preg_match('/#_FIELDNAME(.+)/', $result, $matches)) {
         $field_id = intval($matches[1]);
         $formfield = eme_get_formfield_byid($field_id);
         $replacement = eme_trans_sanitize_html($formfield['field_name']);
      } elseif (preg_match('/#_FIELD(.+)$/', $result, $matches)) {
         $field_id = intval($matches[1]);
         $formfield = eme_get_formfield_byid($field_id);
         foreach ($answers as $answer) {
            if ($answer['field_name'] == $formfield['field_name'])
               $replacement = $answer['answer'];
         }
         if ($target == "html")
            $replacement = apply_filters('eme_general', $replacement); 
         else 
            $replacement = apply_filters('eme_general_rss', $replacement); 
      } else {
         $found = 0;
      }

      if ($need_escape)
         $replacement = eme_sanitize_request(preg_replace('/\n|\r/','',$replacement));

      if ($found)
         $format = str_replace($orig_result, $replacement ,$format );
   }
   return do_shortcode($format);   
}

function eme_replace_attendees_placeholders($format, $attendee, $event_id, $target="html") {
   preg_match_all("/#_?[A-Za-z0-9_]+/", $format, $placeholders);
   foreach($placeholders[0] as $result) {
      $replacement='';
      $found = 1;
      if (preg_match('/#_(ATTEND)?(NAME|PHONE|ID|EMAIL)$/', $result)) {
         $field = preg_replace("/#_ATTEND|#_/","",$result);
         $field = "person_".strtolower($field);
         $replacement = $attendee[$field];
         $replacement = eme_sanitize_html($replacement);
         if ($target == "html")
            $replacement = apply_filters('eme_general', $replacement); 
         else 
            $replacement = apply_filters('eme_general_rss', $replacement); 

      } elseif (preg_match('/#_USER_(RESERVEDSPACES|BOOKEDSEATS)$/', $result)) {
         $replacement = eme_get_booked_seats_by_person_event_id($attendee['person_id'],$event_id);
      } elseif (preg_match('/#_ATTENDSPACES$/', $result)) {
         $replacement = eme_get_booked_seats_by_person_event_id($attendee['person_id'],$event_id);
      } else {
         $found = 0;
      }
      if ($found)
         $format = str_replace($result, $replacement ,$format );
   }
   return do_shortcode($format);   
}

function eme_email_rsvp_booking($booking_id,$action="") {
   // first check if a mail should be send at all
   $mailing_is_active = get_option('eme_rsvp_mail_notify_is_active');
   if (!$mailing_is_active) {
      return;
   }

   $booking = eme_get_booking ($booking_id);
   $answers = eme_get_answers ($booking_id);
   $field_replace = "";
   foreach ($answers as $answer) {
      $field_replace.=$answer['field_name'].": ".$answer['answer']."\n";
   }

   $person = eme_get_person ($booking['person_id']);
   $event = eme_get_event($booking['event_id']);
   $event_name = $event['event_name'];
   $contact = eme_get_contact ($event);
   $contact_email = $contact->user_email;
   $contact_name = $contact->display_name;
   
   $contact_body = ( $event['event_contactperson_email_body'] != '' ) ? $event['event_contactperson_email_body'] : get_option('eme_contactperson_email_body' );
   $contact_body = eme_replace_placeholders($contact_body, $event, "text");
   $contact_body = eme_replace_booking_placeholders($contact_body, $booking, "text");
   $confirmed_body = ( $event['event_respondent_email_body'] != '' ) ? $event['event_respondent_email_body'] : get_option('eme_respondent_email_body' );
   $confirmed_body = eme_replace_placeholders($confirmed_body, $event, "text");
   $confirmed_body = eme_replace_booking_placeholders($confirmed_body, $booking, "text");
   $pending_body = ( $event['event_registration_pending_email_body'] != '' ) ? $event['event_registration_pending_email_body'] : get_option('eme_registration_pending_email_body' );
   $pending_body = eme_replace_placeholders($pending_body, $event, "text");
   $pending_body = eme_replace_booking_placeholders($pending_body, $booking, "text");
   $denied_body = get_option('eme_registration_denied_email_body' );
   $denied_body = eme_replace_placeholders($denied_body, $event, "text");
   $denied_body = eme_replace_booking_placeholders($denied_body, $booking, "text");
   $cancelled_body = get_option('eme_registration_cancelled_email_body' );
   $cancelled_body = eme_replace_placeholders($cancelled_body, $event, "text");
   $cancelled_body = eme_replace_booking_placeholders($cancelled_body, $booking, "text");
   $contact_cancelled_body = get_option('eme_contactperson_cancelled_email_body' );
   $contact_cancelled_body = eme_replace_placeholders($contact_cancelled_body, $event, "text");
   $contact_cancelled_body = eme_replace_booking_placeholders($contact_cancelled_body, $booking, "text");
   $contact_pending_body = get_option('eme_contactperson_pending_email_body' );
   $contact_pending_body = eme_replace_placeholders($contact_pending_body, $event, "text");
   $contact_pending_body = eme_replace_booking_placeholders($contact_pending_body, $booking, "text");

   // total price to pay
   $total_price=eme_get_total_booking_price($event,$booking);
   
   // rsvp specific placeholders
   #$placeholders = array('#_RESPNAME' => $person['person_name'], '#_RESPEMAIL' => $person['person_email'], '#_RESPPHONE' => $person['person_phone'], '#_SPACES' => $booking['booking_seats'],'#_COMMENT' => $booking['booking_comment'], '#_TRANSFER_NBR_BE97' => $booking['transfer_nbr_be97'], '#_TOTALPRICE' => $total_price, '#_FIELDS' => $field_replace );
   $placeholders = array('#_TOTALPRICE' => $total_price );

   foreach($placeholders as $key => $value) {
      $contact_body = str_replace($key, $value, $contact_body);
      $contact_cancelled_body = str_replace($key, $value, $contact_cancelled_body);
      $contact_pending_body = str_replace($key, $value, $contact_pending_body);
      $confirmed_body = str_replace($key, $value, $confirmed_body);
      $pending_body = str_replace($key, $value, $pending_body);
      $denied_body = str_replace($key, $value, $denied_body);
      $cancelled_body = str_replace($key, $value, $cancelled_body);
   }

  // possible translations are handled last 
   $contact_body = eme_translate($contact_body); 
   $contact_cancelled_body = eme_translate($contact_cancelled_body); 
   $contact_pending_body = eme_translate($contact_pending_body); 
   $confirmed_body = eme_translate($confirmed_body); 
   $pending_body = eme_translate($pending_body); 
   $denied_body = eme_translate($denied_body); 
   $cancelled_body = eme_translate($cancelled_body);  
   $event_name = eme_translate($event_name);  

   if($action!="") {
      if ($action == 'approveRegistration') {
         eme_send_mail(sprintf(__("Reservation for '%s' confirmed",'eme'),$event_name),$confirmed_body, $person['person_email'], $person['person_name'], $contact_email, $contact_name);
      } elseif ($action == 'denyRegistration') {
         eme_send_mail(sprintf(__("Reservation for '%s' denied",'eme'),$event_name),$denied_body, $person['person_email'], $person['person_name'], $contact_email, $contact_name);
      } elseif ($action == 'cancelRegistration') {
         eme_send_mail(sprintf(__("Reservation for '%s' cancelled",'eme'),$event_name),$cancelled_body, $person['person_email'], $person['person_name'], $contact_email, $contact_name);
         eme_send_mail(sprintf(__("A reservation has been cancelled for '%s'",'eme'),$event_name), $contact_cancelled_body, $contact_email, $contact_name, $contact_email, $contact_name);
      }
   } else {
      // send different mails depending on approval or not
      if ($event['registration_requires_approval']) {
         eme_send_mail(sprintf(__("Approval required for new booking for '%s'",'eme'),$event_name), $contact_pending_body, $contact_email, $contact_name, $contact_email, $contact_name);
         eme_send_mail(sprintf(__("Reservation for '%s' is pending",'eme'),$event_name),$pending_body, $person['person_email'], $person['person_name'], $contact_email, $contact_name);
      } else {
         eme_send_mail(sprintf(__("New booking for '%s'",'eme'),$event_name), $contact_body, $contact_email,$contact_name, $contact_email, $contact_name);
         eme_send_mail(sprintf(__("Reservation for '%s' confirmed",'eme'),$event_name),$confirmed_body, $person['person_email'], $person['person_name'], $contact_email, $contact_name);
      }
   }
} 

function eme_registration_seats_page() {
   global $wpdb;

   if (current_user_can( get_option('eme_cap_registrations'))) {
      // do the actions if required
      if (isset($_GET['action']) && $_GET['action'] == "delete_bookings" && isset($_GET['bookings'])) {
         $bookings = $_GET['bookings'];
         if (is_array($bookings)) {
            foreach($bookings as $booking_id) {
               eme_delete_booking(intval($booking_id));
            }
         }
      } else {
         if (isset($_POST ['doaction']))
            $action = isset($_POST ['action']) ? $_POST ['action'] : '';
         else
            $action = '';
         $send_mail = isset($_POST ['send_mail']) ? intval($_POST ['send_mail']) : 1;

         if ($action == 'addRegistration') {
            $event_id = intval($_POST['event_id']);
            $booking_payed = isset($_POST ['booking_payed']) ? intval($_POST ['booking_payed']) : 0;
            $event = eme_get_event($event_id);
            $booking_res = eme_book_seats($event, $send_mail);
            $result=$booking_res[0];
            $booking_id_done=$booking_res[1];
            if (!$booking_id_done) {
               print "<div id='message' class='error'><p>$result</p></div>";
            } else {
               print "<div id='message' class='updated'><p>$result</p></div>";
               eme_update_booking_payed($booking_id_done,$booking_payed);
            }
         } elseif ($action == 'approveRegistration' || $action == 'denyRegistration') {
            $bookings = isset($_POST ['bookings']) ? $_POST ['bookings'] : array();
            $selected_bookings = isset($_POST ['selected_bookings']) ? $_POST ['selected_bookings'] : array();
            $bookings_seats = isset($_POST ['bookings_seats']) ? $_POST ['bookings_seats'] : array();
            $bookings_payed = isset($_POST ['bookings_payed']) ? $_POST ['bookings_payed'] : array();

            foreach ( $bookings as $key=>$booking_id ) {
               if (!in_array($booking_id,$selected_bookings)) {
                  continue;
               }
               // make sure the seats are integers
               $booking = eme_get_booking ($booking_id);
               if ($action == 'approveRegistration') {
                  if ($booking['booking_payed']!= intval($bookings_payed[$key]))
                     eme_update_booking_payed($booking_id,intval($bookings_payed[$key]));
                  if ($booking['booking_seats']!= $bookings_seats[$key]) {
                     eme_update_booking_seats($booking_id,$booking['event_id'],$bookings_seats[$key],$booking['booking_price']);
                     if ($send_mail) eme_email_rsvp_booking($booking_id,$action);
                  }
               } elseif ($action == 'denyRegistration') {
                  if ($send_mail) eme_email_rsvp_booking($booking_id,$action);
                  eme_delete_booking($booking_id);
               }
            }
         }
      }
   }
   
   // now show the menu
   $event_id = isset($_POST ['event_id']) ? intval($_POST ['event_id']) : 0;
   eme_registration_seats_form_table($event_id);
}

function eme_registration_seats_form_table($event_id=0) {
?>
<div class="wrap">
<div id="icon-events" class="icon32"><br />
</div>
<h2><?php _e ('Change reserved spaces or cancel registrations','eme'); ?></h2>
<?php admin_show_warnings();?>
   <form id='add-booking' name='add-booking' action="" method="post">
   <input type='hidden' name='page' value='eme-registration-seats' />
   <input type='hidden' name='action' value='addRegistration' />
   <table class="widefat">
   <tbody>
            <tr><th scope='row'><?php _e('Name', 'eme'); ?>*:</th><td><input type='text' name='bookerName' value='' /></td></tr>
            <tr><th scope='row'><?php _e('E-Mail', 'eme'); ?>*:</th><td><input type='text' name='bookerEmail' value='' /></td></tr>
            <tr><th scope='row'><?php _e('Phone number', 'eme'); ?>:</th><td><input type='text' name='bookerPhone' value='' /></td></tr>
            <tr><th scope='row'><?php _e('Event', 'eme'); ?>*:</th><td>
   <select name="event_id">
   <?php
   $all_events=eme_get_events(0,"future");
   $events_with_pending_bookings=array();
   foreach ( $all_events as $event ) {
      if ($event ['event_rsvp']) {
         $option_text=$event['event_name']." (".eme_localised_date($event['event_start_date']).")"; 
         echo "<option value='".$event['event_id']."' >".$option_text."</option>  ";
      }
   }
   ?>
   </select>
                </td>
            </tr>
            <tr><th scope='row'><?php _e('Seats', 'eme'); ?>*:</th><td><input type='text' name='bookedSeats' value='' /></td></tr>
            <tr><th scope='row'><?php _e('Seats (Multiprice)', 'eme'); ?>*:</th><td><input title="<?php _e('For multiprice events, seperate the values by \'||\'','eme'); ?>" type='text' name='bookedSeats_mp' value='' /></td></tr>
            <tr><th scope='row'><?php _e('Paid', 'eme'); ?>:</th><td><?php echo eme_ui_select_binary(0,"booking_payed"); ?></td></tr>
   </tbody>
   </table>
   <p>
   <?php _e('Send mails for new registration?','eme'); echo eme_ui_select_binary(1,"send_mail"); ?>
   </p>
   <input type="submit" name="doaction" id="doaction" class="button-secondary action" value="<?php _e ( 'Register booking','eme' )?>" />
   </form>

   <div class="clear"></div>

   <form id="eme-admin-changeregform" name="eme-admin-changeregform" action="" method="post">
   <input type='hidden' name='page' value='eme-registration-seats' />
   <div class="tablenav">
   <div class="alignleft actions">
   <select name="action">
   <option value="-1" selected="selected"><?php _e ( 'Bulk Actions' ); ?></option>
   <option value="approveRegistration"><?php _e ( 'Update registration','eme' ); ?></option>
   <option value="denyRegistration"><?php _e ( 'Deny registration','eme' ); ?></option>
   </select>
   <input type="submit" name="doaction" id="doaction" class="button-secondary action" value="<?php _e ( 'Apply' )?>" />
   <select name="event_id">
   <option value='0'><?php _e ( 'All events' ); ?></option>
   <?php
   $all_events=eme_get_events(0,"future");
   $events_with_bookings=array();
   foreach ( $all_events as $event ) {
      if (eme_get_approved_seats($event['event_id'])>0) {
         $events_with_bookings[]=$event['event_id'];
         $selected = "";
         if ($event_id && ($event['event_id'] == $event_id))
            $selected = "selected='selected'";
         echo "<option value='".$event['event_id']."' $selected>".$event['event_name']."</option>  ";
      }
   }
   ?>
   </select>
   <input id="post-query-submit" class="button-secondary" type="submit" value="<?php _e ( 'Filter' )?>" />
   </div>
   <div class="clear"><p>
   <?php _e('Send mails to attendees upon changes being made?','eme'); echo eme_ui_select_binary(1,"send_mail"); ?>
   </p></div>
   <table class="widefat">
   <thead>
      <tr>
         <th class='manage-column column-cb check-column' scope='col'><input
            class='select-all' type="checkbox" value='1' /></th>
         <th><?php _e ('ID','eme'); ?></th>
         <th><?php _e ('Name','eme'); ?></th>
         <th><?php _e ('Date and time','eme'); ?></th>
         <th><?php _e ('Booker','eme'); ?></th>
         <th><?php _e ('Seats','eme'); ?></th>
         <th><?php _e ('Event price','eme'); ?></th>
         <th><?php _e ('Total price','eme'); ?></th>
         <th><?php _e ('Unique nbr','eme'); ?></th>
         <th><?php _e ('Paid','eme'); ?></th>
      </tr>
   </thead>
   <tbody>
     <?php
      $i = 1;
      if ($event_id) {
         $bookings = eme_get_bookings_for($event_id,2);
      } else {
         $bookings = eme_get_bookings_for($events_with_bookings,2);
      }
      foreach ( $bookings as $event_booking ) {
         $person = eme_get_person ($event_booking['person_id']);
         $event = eme_get_event($event_booking['event_id']);
         $class = ($i % 2) ? ' class="alternate"' : '';
         $localised_start_date = eme_localised_date($event['event_start_date']);
         $localised_end_date = eme_localised_date($event['event_end_date']);
         $style = "";
         $today = date ( "Y-m-d" );
         
         if ($event['event_start_date'] < $today)
            $style = "style ='background-color: #FADDB7;'";
         ?>
      <tr <?php echo "$class $style"; ?>>
         <td><input type='checkbox' class='row-selector' value='<?php echo $event_booking ['booking_id']; ?>' name='selected_bookings[]' />
             <input type='hidden' class='row-selector' value='<?php echo $event_booking ['booking_id']; ?>' name='bookings[]' /></td>
         <td><?php echo $event_booking ['booking_id']; ?></td>
         <td><strong>
         <a class="row-title" href="<?php echo admin_url("admin.php?page=events-manager&amp;action=edit_event&amp;event_id=".$event_booking ['event_id']); ?>"><?php echo eme_trans_sanitize_html($event ['event_name']); ?></a>
         </strong>
         <?php
             $printable_address = admin_url("/admin.php?page=eme-people&amp;action=booking_printable&amp;event_id=".$event['event_id']);
             $csv_address = admin_url("/admin.php?page=eme-people&amp;action=booking_csv&amp;event_id=".$event['event_id']);
             $approved_seats = eme_get_approved_seats($event['event_id']);
             $pending_seats = eme_get_pending_seats($event['event_id']);
             $total_seats = $event ['event_seats'];
             echo "<br />".__('Approved: ','eme' ).$approved_seats.", ".__('Pending: ','eme').$pending_seats.", ".__('Max: ','eme').$total_seats;
             if ($approved_seats>0) {
                echo " (<a id='booking_printable_".$event['event_id']."'  target='' href='$printable_address'>".__('Printable view','eme')."</a>)";
                echo " (<a id='booking_csv_".$event['event_id']."'  target='' href='$csv_address'>".__('CSV export','eme')."</a>)";
             }
         ?>
         </td>
         <td>
            <?php echo $localised_start_date; if ($localised_end_date !='' && $localised_end_date != $localised_start_date) echo " - " . $localised_end_date; ?><br />
            <?php echo substr ( $event['event_start_time'], 0, 5 ) . " - " . substr ( $event['event_end_time'], 0, 5 ); ?>
         </td>
         <td>
            <?php echo eme_sanitize_html($person['person_name']) ."(".eme_sanitize_html($person['person_phone']).", ". eme_sanitize_html($person['person_email']).")";?>
         </td>
         <?php if (eme_is_multiprice(eme_get_booking_price($event,$event_booking))) { ?>
         <td>
            <input title="<?php _e('For multiprice events, seperate the values by \'||\'','eme'); ?>" type="text" name="bookings_seats[]" value="<?php echo $event_booking['booking_seats_mp']; ?>" /><?php _e('(Multiprice)','eme');?>
         </td>
         <?php } else { ?>
         <td>
            <input type="text" name="bookings_seats[]" value="<?php echo $event_booking['booking_seats'];?>" />
         </td>
         <?php } ?>
         <td>
            <?php echo eme_get_booking_price($event,$event_booking); ?>
         </td>
         <td>
            <?php echo eme_get_total_booking_price($event,$event_booking); ?>
         </td>
         <td>
            <?php echo eme_sanitize_html($event_booking['transfer_nbr_be97']); ?>
         </td>
         <td>
            <?php echo eme_ui_select_binary($event_booking['booking_payed'],"bookings_payed[]"); ?>
         </td>
      </tr>
      <?php
         $i++;
      }
      ?>
   </tbody>
   </table>

   <div class='tablenav'>
   <div class="alignleft actions"><br class='clear' />
   </div>
   <br class='clear' />
   </div>

   </div>
   </form>
</div>
<?php
}
function eme_registration_approval_page() {
        global $wpdb;

   if (current_user_can( get_option('eme_cap_approve'))) {
      // do the actions if required
      if (isset($_POST ['doaction']))
         $action = isset($_POST ['action']) ? $_POST ['action'] : '';
      else
         $action='';
      $pending_bookings = isset($_POST ['pending_bookings']) ? $_POST ['pending_bookings'] : array();
      $selected_bookings = isset($_POST ['selected_bookings']) ? $_POST ['selected_bookings'] : array();
      $bookings_seats = isset($_POST ['bookings_seats']) ? $_POST ['bookings_seats'] : array();
      $bookings_payed = isset($_POST ['bookings_payed']) ? $_POST ['bookings_payed'] : array();
      $send_mail = isset($_POST ['send_mail']) ? intval($_POST ['send_mail']) : 1;
      foreach ( $pending_bookings as $key=>$booking_id ) {
         if (!in_array($booking_id,$selected_bookings)) {
            continue;
         }
         $booking = eme_get_booking ($booking_id);
         // update the db
         if ($action == 'approveRegistration') {
            eme_approve_booking($booking_id);
            // 0 seats is not possible, then you should remove the booking
            if ($bookings_seats[$key]==0)
               $bookings_seats[$key]=1;
            if ($booking['booking_payed']!= intval($bookings_payed[$key]))
               eme_update_booking_payed($booking_id,intval($bookings_payed[$key]));
            if ($booking['booking_seats']!= $bookings_seats[$key]) {
               eme_update_booking_seats($booking_id,$booking['event_id'],$bookings_seats[$key],$booking['booking_price']);
            }
            if ($send_mail) eme_email_rsvp_booking($booking_id,$action);
         } elseif ($action == 'denyRegistration') {
            if ($send_mail) eme_email_rsvp_booking($booking_id,$action);
            eme_delete_booking($booking_id);
         }
      }
   }
   // now show the menu
   $event_id = isset($_POST ['event_id']) ? intval($_POST ['event_id']) : 0;
   eme_registration_approval_form_table($event_id);
}

function eme_registration_approval_form_table($event_id=0) {
?>
<div class="wrap">
<div id="icon-events" class="icon32"><br />
</div>
<h2><?php _e ('Pending Approvals','eme'); ?></h2>
<?php admin_show_warnings();?>
   <form id="eme-admin-pendingform" name="eme-admin-pendingform" action="" method="post">
   <input type='hidden' name='page' value='eme-registration-approval' />
   <div class="tablenav">
   <div class="alignleft actions">
   <select name="action">
   <option value="-1" selected="selected"><?php _e ( 'Bulk Actions' ); ?></option>
   <option value="approveRegistration"><?php _e ( 'Approve registration','eme' ); ?></option>
   <option value="denyRegistration"><?php _e ( 'Deny registration','eme' ); ?></option>
   </select>
   <input type="submit" value="<?php _e ( 'Apply' ); ?>" name="doaction" id="doaction" class="button-secondary action" />
   <select name="event_id">
   <option value='0'><?php _e ( 'All events' ); ?></option>
   <?php
   $all_events=eme_get_events(0,"future");
   $events_with_pending_bookings=array();
   foreach ( $all_events as $event ) {
      if (eme_get_pending_bookings($event['event_id'])>0) {
         $events_with_pending_bookings[]=$event['event_id'];
         $selected = "";
         if ($event_id && ($event['event_id'] == $event_id))
            $selected = "selected='selected'";
         echo "<option value='".$event['event_id']."' $selected>".$event['event_name']."</option>  ";
      }
   }
   ?>
   </select>
   <input id="post-query-submit" class="button-secondary" type="submit" value="<?php _e ( 'Filter' )?>" />
   </div>
   <div class="clear"><p>
   <?php _e('Send mails to attendees upon changes being made?','eme'); echo eme_ui_select_binary(1,"send_mail"); ?>
   </p></div>
   <table class="widefat">
   <thead>
      <tr>
         <th class='manage-column column-cb check-column' scope='col'><input
            class='select-all' type="checkbox" value='1' /></th>
         <th><?php _e ( 'ID', 'eme' ); ?></th>
         <th><?php _e ( 'Name', 'eme' ); ?></th>
         <th><?php _e ( 'Date and time', 'eme' ); ?></th>
         <th><?php _e ('Booker','eme'); ?></th>
         <th><?php _e ('Seats','eme'); ?></th>
         <th><?php _e ('Event price','eme'); ?></th>
         <th><?php _e ('Total price','eme'); ?></th>
         <th><?php _e ('Unique nbr','eme'); ?></th>
         <th><?php _e ('Paid','eme'); ?></th>
      </tr>
   </thead>
   <tbody>
     <?php
      $i = 1;
      if ($event_id) {
         $pending_bookings = eme_get_bookings_for($event_id,1);
      } else {
         $pending_bookings = eme_get_bookings_for($events_with_pending_bookings,1);
      }
      foreach ( $pending_bookings as $event_booking ) {
         $person = eme_get_person ($event_booking['person_id']);
         $event = eme_get_event($event_booking['event_id']);
         $class = ($i % 2) ? ' class="alternate"' : '';
         $localised_start_date = eme_localised_date($event['event_start_date']);
         $localised_end_date = eme_localised_date($event['event_end_date']);
         $style = "";
         $today = date ( "Y-m-d" );
         
         if ($event['event_start_date'] < $today)
            $style = "style ='background-color: #FADDB7;'";
         ?>
      <tr <?php echo "$class $style"; ?>>
         <td><input type='checkbox' class='row-selector' value='<?php echo $event_booking ['booking_id']; ?>' name='selected_bookings[]' /></td>
             <input type='hidden' class='row-selector' value='<?php echo $event_booking ['booking_id']; ?>' name='pending_bookings[]' /></td>
         <td><?php echo $event_booking ['booking_id']; ?></td>
         <td><strong>
         <a class="row-title" href="<?php echo admin_url("admin.php?page=events-manager&amp;action=edit_event&amp;event_id=".$event_booking ['event_id']); ?>"><?php echo eme_sanitize_html($event ['event_name']); ?></a>
         </strong>
         <?php
             $approved_seats = eme_get_approved_seats($event['event_id']);
             $pending_seats = eme_get_pending_seats($event['event_id']);
             $total_seats = $event ['event_seats'];
             echo "<br />".__('Approved: ','eme' ).$approved_seats.", ".__('Pending: ','eme').$pending_seats.", ".__('Max: ','eme').$total_seats;
         ?>
         </td>
         <td>
            <?php echo $localised_start_date; if ($localised_end_date !='') echo " - " . $localised_end_date; ?><br />
            <?php echo substr ( $event['event_start_time'], 0, 5 ) . " - " . substr ( $event['event_end_time'], 0, 5 ); ?>
         </td>
         <td>
            <?php echo eme_sanitize_html($person['person_name']) ."(".eme_sanitize_html($person['person_phone']).", ". eme_sanitize_html($person['person_email']).")";?>
         </td>
         <?php if (eme_is_multiprice(eme_get_booking_price($event,$event_booking))) { ?>
         <td>
            <input title="<?php _e('For multiprice events, seperate the values by \'||\'','eme'); ?>" type="text" name="bookings_seats[]" value="<?php echo $event_booking['booking_seats_mp']; ?>" /><?php _e('(Multiprice)','eme');?>
         </td>
         <?php } else { ?>
         <td>
            <input type="text" name="bookings_seats[]" value="<?php echo $event_booking['booking_seats'];?>" />
         </td>
         <?php } ?>
         <td>
            <?php echo eme_get_booking_price($event,$event_booking); ?>
         </td>
         <td>
            <?php echo eme_get_total_booking_price($event,$event_booking); ?>
         </td>
         <td>
            <?php echo eme_sanitize_html($event_booking['transfer_nbr_be97']); ?>
         </td>
         <td>
            <?php echo eme_ui_select_binary($event_booking['booking_payed'],"bookings_payed[]"); ?>
         </td>
      </tr>
      <?php
         $i++;
      }
      ?>
   </tbody>
   </table>

   <div class='tablenav'>
   <div class="alignleft actions"><br class='clear' />
   </div>
   <br class='clear' />
   </div>

   </div>
   </form>
</div>

<?php
}

function eme_send_mails_page() {
   global $wpdb;

   $event_id = isset($_POST ['event_id']) ? intval($_POST ['event_id']) : 0;
   if (isset($_POST ['doaction']))
      $action = isset($_POST ['action']) ? $_POST ['action'] : '';
   else
      $action = '';
   $message = isset($_POST ['message']) ? $_POST ['message'] : '';
   $subject = isset($_POST ['subject']) ? $_POST ['subject'] : '';

   if ($event_id>0 && $action == 'send_mail') {
      $pending_approved = isset($_POST ['pending_approved']) ? $_POST ['pending_approved'] : 0;
      $only_unpayed = isset($_POST ['only_unpayed']) ? $_POST ['only_unpayed'] : 0;
      $target = isset($_POST ['target']) ? $_POST ['target'] : 'attendees';
	   if (empty($subject) || empty($message)) {
		   print "<div id='message' class='error'><p>".__('Please enter both subject and message for the mail to be sent.','eme')."</p></div>";
	   } else {
		   $event = eme_get_event($event_id);
		   $current_userid=get_current_user_id();
		   if (current_user_can( get_option('eme_cap_send_other_mails')) ||
				   (current_user_can( get_option('eme_cap_send_mails')) && ($event['event_author']==$current_userid || $event['event_contactperson_id']==$current_userid))) {  

			   $event_name = $event['event_name'];
			   $contact = eme_get_contact ($event);
			   $contact_email = $contact->user_email;
			   $contact_name = $contact->display_name;

			   $message = eme_replace_placeholders($message, $event, "text");
			   $subject = eme_replace_placeholders($subject, $event, "text");

            if ($target == 'attendees') {
               $attendees = eme_get_attendees_for($event_id,$pending_approved,$only_unpayed);
               foreach ( $attendees as $attendee ) {
                  $tmp_message = eme_replace_attendees_placeholders($message, $attendee, $event_id, "text");
                  $tmp_message = eme_translate($tmp_message);
                  $tmp_message = eme_strip_tags($tmp_message);
                  $tmp_subject = eme_replace_attendees_placeholders($subject, $attendee, $event_id, "text");
                  $tmp_subject = eme_translate($tmp_subject);
                  $tmp_subject = eme_strip_tags($tmp_subject);
                  eme_send_mail($tmp_subject,$tmp_message, $attendee['person_email'], $attendee['person_name'], $contact_email, $contact_name);
               }
            } elseif ($target == 'bookings') {
               $bookings = eme_get_bookings_for($event_id,$pending_approved,$only_unpayed);
               foreach ( $bookings as $booking ) {
                  $attendee = eme_get_person($booking['person_id']);
                  if ($attendee && is_array($attendee)) {
                     $tmp_message = eme_replace_booking_placeholders($message, $booking, "text");
                     $tmp_message = eme_translate($tmp_message);
                     $tmp_message = eme_strip_tags($tmp_message);
                     $tmp_subject = eme_replace_booking_placeholders($subject, $booking, "text");
                     $tmp_subject = eme_translate($tmp_subject);
                     $tmp_subject = eme_strip_tags($tmp_subject);
                     eme_send_mail($tmp_subject,$tmp_message, $attendee['person_email'], $attendee['person_name'], $contact_email, $contact_name);
                  }
               }
			   }
			   print "<div id='message' class='updated'><p>".__('The mail has been sent.','eme')."</p></div>";
		   } else {
			   print "<div id='message' class='error'><p>".__('You do not have the permission to send mails for this event.','eme')."</p></div>";
		   }
	   }
   }

   // now show the form
   eme_send_mail_form($event_id);
}

function eme_send_mail_form($event_id=0) {
?>
<div class="wrap">
<div id="icon-events" class="icon32"><br />
</div>
<h2><?php _e ('Send Mails to attendees or bookings for a event','eme'); ?></h2>
<?php admin_show_warnings();?>
   <div id='message' class='updated'><p>
<?php
      _e('Warning: using this functionality to send mails to attendees can result in a php timeout, so not everybody will receive the mail then. This depends on the number of attendees, the load on the server, ... . If this happens, use the CSV export link to get the list of all attendees and use mass mailing tools (like OpenOffice) for your mailing.','eme');
?>
   </p></div>
   <form id='send-mail' name='send-mail' action="" method="post">
   <input type='hidden' name='page' value='eme-send-mails' />
   <input type='hidden' name='action' value='send_mail' />
   <select name="event_id" onchange="this.form.submit()">
   <?php
   $all_events=eme_get_events(0,"future");
   $event_id = isset($_POST ['event_id']) ? intval($_POST ['event_id']) : 0;
   $current_userid=get_current_user_id();
   echo "<option value='0' >".__('Select the event','eme')."</option>  ";
   foreach ( $all_events as $event ) {
         $option_text=$event['event_name']." (".eme_localised_date($event['event_start_date']).")";
	 if ($event['event_rsvp'] && current_user_can( get_option('eme_cap_send_other_mails')) ||
			 (current_user_can( get_option('eme_cap_send_mails')) && ($event['event_author']==$current_userid || $event['event_contactperson_id']==$current_userid))) {  
		 if ($event['event_id'] == $event_id) {
			 echo "<option selected='selected' value='".$event['event_id']."' >".$option_text."</option>  ";
		 } else {
			 echo "<option value='".$event['event_id']."' >".$option_text."</option>  ";
		 }
	 }
   }
   ?>
   </select>
   <p>
   <?php if ($event_id>0) {?>
      <table>
      <tr>
	   <td><label><?php _e('Select the type of mail','eme'); ?></td>
      <td>
           <select name="target">
           <option value='attendees'><?php _e('Attendee mails','eme'); ?></option>
           <option value='bookings'><?php _e('Booking mails','eme'); ?></option>
           </select>
      </td>
      </tr>
      <tr>
	   <td><label><?php _e('Select your target audience','eme'); ?></td>
      <td>
           <select name="pending_approved">
           <option value=0><?php _e('All','eme'); ?></option>
           <option value=2><?php _e('Exclude pending registrations','eme'); ?></option>
           <option value=1><?php _e('Only pending registrations','eme'); ?></option>
           </select></p><p>
      </td>
      </tr>
      <tr>
      <td><?php _e('Only send mails to attendees who did not pay yet','eme'); ?>&nbsp;</td>
      <td>
           <input type="checkbox" name="only_unpayed" value="1" />
      </td>
      </tr>
      </table>
	   <div id="titlediv" class="form-field form-required"><p>
		   <label><?php _e('Subject','eme'); ?></label><br>
		   <input type="text" name="subject" value="" /></p>
	   </div>
	   <div class="form-field form-required"><p>
	   <label><?php _e('Message','eme'); ?></label><br>
	   <textarea name="message" value="" rows=10></textarea> </p>
	   </div>
	   <div>
	   <?php _e('You can use any placeholders mentioned here:','eme');
	   print "<br><a href='http://www.e-dynamics.be/wordpress/?cat=25'>".__('Event placeholders','eme')."</a>";
	   print "<br><a href='http://www.e-dynamics.be/wordpress/?cat=48'>".__('Attendees placeholders','eme')."</a> (".__('for ','eme').__('Attendee mails','eme').")";
	   print "<br><a href='http://www.e-dynamics.be/wordpress/?cat=45'>".__('Booking placeholders','eme')."</a> (".__('for ','eme').__('Booking mails','eme').")";
	   ?>
	   </div>
      <br />
	   <input type="submit" value="<?php _e ( 'Send Mail', 'eme' ); ?>" name="doaction" id="doaction" class="button-secondary action" />
	   </form>

   <?php
	   $csv_address = admin_url("/admin.php?page=eme-people&amp;action=booking_csv&amp;event_id=".$event['event_id']);
	   $available_seats = eme_get_available_seats($event['event_id']);
	   $total_seats = $event ['event_seats'];
	   if ($total_seats!=$available_seats)
		   echo "<br><br> <a id='booking_csv_".$event['event_id']."'  target='' href='$csv_address'>".__('CSV export','eme')."</a>";
   }
}

function eme_webmoney_form($event,$booking_id) {
   $booking = eme_get_booking($booking_id);
   $events_page_link = eme_get_events_page(true, false);
   $name = eme_sanitize_html(sprintf(__("Booking for '%s'","eme"),$event['event_name']));
   $price=eme_get_total_booking_price($event,$booking);

   require_once('webmoney/webmoney.inc.php');
   $wm_request = new WM_Request();
   $wm_request->payment_amount =$price;
   $wm_request->payment_desc = $name;
   $wm_request->payment_no = $booking_id;
   $wm_request->payee_purse = get_option('eme_webmoney_purse');
   $wm_request->success_method = WM_POST;
   if (stristr ( $events_page_link, "?" ))
      $joiner = "&amp;";
   else
      $joiner = "?";
   $result_link = $events_page_link.$joiner."eme_eventAction=webmoney";
   $wm_request->result_url = $result_link;
   $wm_request->success_url = eme_event_url($event);
   $wm_request->fail_url = eme_event_url($event);
   if (get_option('eme_webmoney_demo')) {
      $wm_request->sim_mode = WM_ALL_SUCCESS;
   }
   $wm_request->btn_label = 'Pay via Webmoney';

   $form_html = "<br>".__("You can pay for this event via 2Checkout. If you wish to do so, click the button below.",'eme');
   $form_html .= $wm_request->SetForm(false);
   return $form_html;
}

function eme_2co_form($event,$booking_id) {
   $booking = eme_get_booking($booking_id);
   $events_page_link = eme_get_events_page(true, false);
   $business=get_option('eme_2co_business');
   $url=CO_URL;
   $name = eme_sanitize_html(sprintf(__("Booking for '%s'","eme"),$event['event_name']));
   $price=eme_get_total_booking_price($event,$booking);
   $quantity=1;
   $cur=$event['currency'];

   $form_html = "<br>".__("You can pay for this event via 2Checkout. If you wish to do so, click the button below.",'eme');
   $form_html.="<form action='$url' method='post'>";
   $form_html.="<input type='hidden' name='sid' value='$business' >";
   $form_html.="<input type='hidden' name='mode' value='2CO' >";
   $form_html.="<input type='hidden' name='li_0_type' value='product' >";
   $form_html.="<input type='hidden' name='li_0_product_id' value='$booking_id' >";
   $form_html.="<input type='hidden' name='li_0_name' value='$name' >";
   $form_html.="<input type='hidden' name='li_0_price' value='$price' >";
   $form_html.="<input type='hidden' name='li_0_quantity' value='$quantity' >";
   $form_html.="<input type='hidden' name='currency_code' value='$cur' >";
   $form_html.="<input name='submit' type='submit' value='Pay via 2Checkout' >";
   if (get_option('eme_2co_demo')) {
      $form_html.="<input type='hidden' name='demo' value='Y' >";
   }
   $form_html.="</form>";
   return $form_html;
}

function eme_google_form($event,$booking_id) {
   $booking = eme_get_booking($booking_id);
   $price=eme_get_total_booking_price($event,$booking);
   $quantity=1;
   $events_page_link = eme_get_events_page(true, false);

   require_once('google_checkout/googlecart.php');
   require_once('google_checkout/googleitem.php');
   $merchant_id = get_option('eme_google_merchant_id');  // Your Merchant ID
   $merchant_key = get_option('eme_google_merchant_key');  // Your Merchant Key
   $server_type = get_option('eme_google_checkout_type');
   $cart = new GoogleCart($merchant_id, $merchant_key, $server_type, $event['currency']);
   $item_1 = new GoogleItem("Booking", // Item name
                            sprintf(__("Booking for '%s'","eme"),eme_sanitize_html($event['event_name'])), // Item description
                            $quantity, // Quantity
                            $price); // Unit price
   $item_1->SetMerchantItemId($booking_id);
   $cart->AddItem($item_1);
   $form_html = "<br>".__("You can pay for this event via Google Checkout. If you wish to do so, click the button below.",'eme');
   return $form_html.$cart->CheckoutButtonCode("SMALL");
}

function eme_paypal_form($event,$booking_id) {
   $booking = eme_get_booking($booking_id);
   $price=eme_get_total_booking_price($event,$booking);
   $quantity=1;
   $events_page_link = eme_get_events_page(true, false);
   if (stristr ( $events_page_link, "?" ))
      $joiner = "&amp;";
   else
      $joiner = "?";
   $notification_link = $events_page_link.$joiner."eme_eventAction=paypal_notification";

   $form_html = "<br>".__("You can pay for this event via paypal. If you wish to do so, click the 'Pay via Paypal' button below.",'eme');
   require_once "paypal/Paypal.php";
   $p = new Paypal;

   // the paypal or paypal sandbox url
   $p->paypal_url = get_option('eme_paypal_url');

   // the timeout in seconds before the button form is submitted to paypal
   // this needs the included addevent javascript function
   // 0 = no delay
   // false = disable auto submission
   $p->timeout = false;

   // the button label
   // false to disable button (if you want to rely only on the javascript auto-submission) not recommended
   $p->button = __('Pay via Paypal','eme');

   if (get_option('eme_paypal_s_encrypt')) {
      // use encryption (strongly recommended!)
      $p->encrypt = true;
      $p->private_key = get_option('eme_paypal_s_privkey');
      $p->public_cert = get_option('eme_paypal_s_pubcert');
      $p->paypal_cert = get_option('eme_paypal_s_paypalcert');
      $p->cert_id = get_option('eme_paypal_s_certid');
   } else {
      $p->encrypt = false;
   }

   // the actual button parameters
   // https://www.paypal.com/IntegrationCenter/ic_std-variable-reference.html
   $p->add_field('charset','utf-8');
   $p->add_field('business', get_option('eme_paypal_business'));
   $p->add_field('return', eme_event_url($event));
   $p->add_field('cancel_return', eme_event_url($event));
   $p->add_field('notify_url', $notification_link);
   $p->add_field('item_name', sprintf(__("Booking for '%s'","eme"),eme_sanitize_html($event['event_name'])));
   $p->add_field('item_number', $booking_id);
   $p->add_field('currency_code',$event['currency']);
   $p->add_field('amount', $price);
   $p->add_field('quantity', $quantity);

   $form_html .= $p->get_button();
   return $form_html;
}

function eme_paypal_notification() {
   require_once 'paypal/IPN.php';
   $ipn = new IPN;

   // the paypal url, or the sandbox url, or the ipn test url
   //$ipn->paypal_url = 'https://www.paypal.com/cgi-bin/webscr';
   $ipn->paypal_url = get_option('eme_paypal_url');

   // your paypal email (the one that receives the payments)
   $ipn->paypal_email = get_option('eme_paypal_business');

   // log to file options
   $ipn->log_to_file = false;					// write logs to file
   $ipn->log_filename = '/path/to/ipn.log';  	// the log filename (should NOT be web accessible and should be writable)

   // log to e-mail options
   $ipn->log_to_email = false;					// send logs by e-mail
   $ipn->log_email = '';		// where you want to receive the logs
   $ipn->log_subject = 'IPN Log: ';			// prefix for the e-mail subject

   // database information
   $ipn->log_to_db = false;						// false not recommended
   $ipn->db_host = 'localhost';				// database host
   $ipn->db_user = '';				// database user
   $ipn->db_pass = '';			// database password
   $ipn->db_name = '';						// database name

   // array of currencies accepted or false to disable
   //$ipn->currencies = array('USD','EUR');
   $ipn->currencies = false;

   // date format on log headers (default: dd/mm/YYYY HH:mm:ss)
   // see http://php.net/date
   $ipn->date_format = 'd/m/Y H:i:s';

   // Prefix for file and mail logs
   $ipn->pretty_ipn = "IPN Values received:\n\n";

   // configuration ended, do the actual check

   if($ipn->ipn_is_valid()) {
      /*
         A valid ipn was received and passed preliminary validations
         You can now do any custom validations you wish to ensure the payment was correct
         You can access the IPN data with $ipn->ipn['value']
         The complete() method below logs the valid IPN to the places you choose
       */
      $booking_id=$ipn->ipn['item_number'];
      eme_update_booking_payed($booking_id,1);
      $ipn->complete();
   }
}

function eme_google_notification() {
  // this function is here for google payment handling, but since that
  // needs a certificate, I don't use it yet
  // Even for just the callback uri, https is required if not using the sandbox
  require_once('google_checkout/googleresponse.php');
  require_once('google_checkout/googleresult.php');
  require_once('google_checkout/googlerequest.php');

  define('RESPONSE_HANDLER_ERROR_LOG_FILE', 'googleerror.log');
  define('RESPONSE_HANDLER_LOG_FILE', 'googlemessage.log');

  $merchant_id = get_option('eme_google_merchant_id');  // Your Merchant ID
  $merchant_key = get_option('eme_google_merchant_key');  // Your Merchant Key
  $server_type = get_option('eme_google_checkout_type');

  $Gresponse = new GoogleResponse($merchant_id, $merchant_key);
  $Grequest = new GoogleRequest($merchant_id, $merchant_key, $server_type, $event['currency']);
  $GRequest->SetCertificatePath($certificate_path);

  //Setup the log file
  //$Gresponse->SetLogFiles(RESPONSE_HANDLER_ERROR_LOG_FILE, 
  //                                      RESPONSE_HANDLER_LOG_FILE, L_ALL);

  // Retrieve the XML sent in the HTTP POST request to the ResponseHandler
  $xml_response = isset($HTTP_RAW_POST_DATA)?
                    $HTTP_RAW_POST_DATA:file_get_contents("php://input");
  if (get_magic_quotes_gpc()) {
    $xml_response = stripslashes($xml_response);
  }
  list($root, $data) = $Gresponse->GetParsedXML($xml_response);
  $Gresponse->SetMerchantAuthentication($merchant_id, $merchant_key);

  /*$status = $Gresponse->HttpAuthentication();
  if(! $status) {
    die('authentication failed');
  }*/

  /* Commands to send the various order processing APIs
   * Send charge order : $Grequest->SendChargeOrder($data[$root]
   *    ['google-order-number']['VALUE'], <amount>);
   * Send process order : $Grequest->SendProcessOrder($data[$root]
   *    ['google-order-number']['VALUE']);
   * Send deliver order: $Grequest->SendDeliverOrder($data[$root]
   *    ['google-order-number']['VALUE'], <carrier>, <tracking-number>,
   *    <send_mail>);
   * Send archive order: $Grequest->SendArchiveOrder($data[$root]
   *    ['google-order-number']['VALUE']);
   *
   */

  switch ($root) {
    case "request-received": {
      break;
    }
    case "error": {
      break;
    }
    case "diagnosis": {
      break;
    }
    case "checkout-redirect": {
      break;
    }
    case "merchant-calculation-callback": {
      break;
    }
    case "new-order-notification": {
      $Gresponse->SendAck();
      break;
    }
    case "order-state-change-notification": {
      $Gresponse->SendAck();
      $new_financial_state = $data[$root]['new-financial-order-state']['VALUE'];
      $new_fulfillment_order = $data[$root]['new-fulfillment-order-state']['VALUE'];

      switch($new_financial_state) {
        case 'REVIEWING': {
          break;
        }
        case 'CHARGEABLE': {
          $Grequest->SendProcessOrder($data[$root]['google-order-number']['VALUE']);
          $Grequest->SendChargeOrder($data[$root]['google-order-number']['VALUE'],'');
          break;
        }
        case 'CHARGING': {
          break;
        }
        case 'CHARGED': {
          $booking_id=$data[$root]['google-order-number']['VALUE'];
          eme_update_booking_payed($booking_id,1);
          break;
        }
        case 'PAYMENT_DECLINED': {
          break;
        }
        case 'CANCELLED': {
          break;
        }
        case 'CANCELLED_BY_GOOGLE': {
          //$Grequest->SendBuyerMessage($data[$root]['google-order-number']['VALUE'],
          //    "Sorry, your order is cancelled by Google", true);
          break;
        }
        default:
          break;
      }

      break;
    }
    case "charge-amount-notification": {
      //$Grequest->SendDeliverOrder($data[$root]['google-order-number']['VALUE'],
      //    <carrier>, <tracking-number>, <send-email>);
      //$Grequest->SendArchiveOrder($data[$root]['google-order-number']['VALUE'] );
      $Gresponse->SendAck();
      break;
    }
    case "chargeback-amount-notification": {
      $Gresponse->SendAck();
      break;
    }
    case "refund-amount-notification": {
      $Gresponse->SendAck();
      break;
    }
    case "risk-information-notification": {
      $Gresponse->SendAck();
      break;
    }
    default:
      $Gresponse->SendBadRequestStatus("Invalid or not supported Message");
      break;
  }
}

function eme_2co_notification() {
   $business=get_option('eme_2co_business');
   $secret=get_option('eme_2co_secret');

   if ($_POST['message_type'] == 'ORDER_CREATED'
       || $_POST['message_type'] == 'INVOICE_STATUS_CHANGED') {
      $insMessage = array();
      foreach ($_POST as $k => $v) {
         $insMessage[$k] = $v;
      }
 
      $hashSid = $insMessage['vendor_id'];
      if ($hashSid != $business) {
         die ('Not the 2Checkout Account number it should be ...');
      }
      $hashOrder = $insMessage['sale_id'];
      $hashInvoice = $insMessage['invoice_id'];
      $StringToHash = strtoupper(md5($hashOrder . $hashSid . $hashInvoice . $secret));
 
      if ($StringToHash != $insMessage['md5_hash']) {
         die('Hash Incorrect');
      }

      $booking_id=$insMessage['item_id_1'];
      // TODO: do some extra checks, like the price payed and such
      #$booking=eme_get_booking($booking_id);
      #$event = eme_get_event($booking['event_id']);
 
      if ($insMessage['invoice_status'] == 'approved' || $insMessage['invoice_status'] == 'deposited') {
          eme_update_booking_payed($booking_id,1);
      }
   }
}

function eme_webmoney_notification() {
   $webmoney_purse = get_option('eme_webmoney_purse');
   $webmoney_secret = get_option('eme_webmoney_secret');

   require_once('webmoney/webmoney.inc.php');
   $wm_notif = new WM_Notification(); 
   if ($wm_notif->GetForm() != WM_RES_NOPARAM) {
      $booking_id=$wm_notif->payment_no;
      $booking=eme_get_booking($booking_id);
      $event = eme_get_event($booking['event_id']);
      $amount=$wm_notif->payment_amount;
      if ($webmoney_purse != $wm_notif->payee_purse) {
         die ('Not the webmoney purse it should be ...');
      }
      #if ($booking['event_seats']*$event['price'] != $amount) {
      #   die ('Not the webmoney amount I expected ...');
      #}
      if ($wm_notif->CheckMD5($webmoney_purse, $amount, $booking_id, $webmoney_secret) == WM_RES_OK) {
          eme_update_booking_payed($booking_id,1);
      }
   }
}

// template function
function eme_is_event_rsvpable() {
   if (eme_is_single_event_page() && isset($_REQUEST['event_id'])) {
      $event = eme_get_event(intval($_REQUEST['event_id']));
      if($event)
         return $event['event_rsvp'];
   }
   return 0;
}

function eme_event_needs_approval($event_id) {
   global $wpdb;
   $events_table = $wpdb->prefix . EVENTS_TBNAME;
   $sql = $wpdb->prepare("SELECT registration_requires_approval from $events_table where event_id=%d",$event_id);
   return $wpdb->get_var( $sql );
}

function eme_get_booking_price($event,$booking) {
   if ($booking['booking_price']!=="")
      $basic_price=$booking['booking_price'];
   else
      $basic_price=$event['price'];
   return $basic_price;
}

function eme_get_total_booking_price($event,$booking) {
   $price=0;
   $basic_price= eme_get_booking_price($event,$booking);

   if (eme_is_multiprice($basic_price)) {
      $prices=preg_split("/\|\|/",$basic_price);
      $seats=preg_split("/\|\|/",$booking['booking_seats_mp']);
      foreach ($prices as $key=>$val) {
         $price += $val*$seats[$key];
      }
   } else {
      $price = $basic_price*$booking['booking_seats'];
   }
   return $price;
}

function eme_is_event_multiprice($event_id) {
   global $wpdb;
   $events_table = $wpdb->prefix . EVENTS_TBNAME;
   $sql = $wpdb->prepare("SELECT price from $events_table where event_id=%d",$event_id);
   $price = $wpdb->get_var( $sql );
   return eme_is_multiprice($price);
}

function eme_is_multiprice($price) {
   global $wpdb;
   if (preg_match("/\|\|/",$price))
      return 1;
   else
      return 0;
}


?>
