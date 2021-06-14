<?php
/**
 * Plugin Name:       Wp to Laravel Data Transfer
 * Description:       Used to send data from E-form to Laravel in encoded form.
 * Version:           1.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Anuj Shrestha
 * License:           GPL v2 or later
 * Text Domain:       wp-to-laravel
 
 Wp to Laravel Data  Transfer is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or any later version.
 
 Wp to Laravel Data  Transfer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with  Wp to Laravel Data  Transfer. 
*/

//Check if Wordpress is installed properely
	if(!function_exists('add_action')){
		echo "You cannot access the files over here";
		exit;
	}

//Activation
	function activation(){
			flush_rewrite_rules(); 	//When the user activates and deactivates the changes associated are performbed 
	}

//Deactivation
	function deactivation(){
		flush_rewrite_rules();	//Flush Rewrite Rules
	}

//Callback Function for extracting data from DB and posting to url https://dash.sipshala.com/api/individuals
	add_action( 'ipt_fsqm_hook_save_insert', 'send_data', 10, 1 );//Execute function when new data insert in E-form
	function send_data(){
		//Test connection to API without any authentication parameters. https://dash.sipshala.com/api/login
			$url = 'https://dash.sipshala.com/api/login';
			$body = array( 
					'username' 	=> 'Wordpress',
					'password' 	=> 'wordpress123',
					'device_name'=> 'wordpress');
			$data = array(
				'method' => 'POST',
				'timeout' => 30,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking' => true,
				// 'headers' => $headers,//No  headers
				'body' => $body,
				'cookies' => array()
				);
			$response = wp_remote_post($url, $data);
			$decoded = json_decode($response['body']);//$decoded->token gives you token
		//After Succesful Response from https://dash.sipshala.com/api/login and getting token
			if($decoded->token): //If we received the token
				// CF7 Technique to get Form Data using sql query
				  global $wpdb; 
				  $base_prefix =$wpdb->base_prefix;

				  $sql = 'SELECT * FROM'.$base_prefix.'fsq_data WHERE form_id = 2 ORDER BY id DESC LIMIT 1';

				  $results = $wpdb -> get_results($sql);

				  $final_data_array = [ ];
				  
				  foreach($results as $result) {
				  	$mcq_val 			= unserialize($result -> mcq);
				  	$freetype_val = unserialize($result -> freetype);
				  	$pinfo_val		= unserialize($result -> pinfo);
				  	$id           = $result -> id;
				  	
				  	//First Page of Form
						  $final_data = [];
						  $final_data['via']														= 'WORDPRESS';
						  $final_data['extra-ID']												= $id;
						  $final_data['name'] 													= $pinfo_val[9]['value'];
						  $final_data['email'] 													= $pinfo_val[6]['value'];
						  $final_data['mobile_number'] 									= $pinfo_val[8]['value'];
						  $final_data['contact_number'] 								= $pinfo_val[5]['value'];
						  $final_data['permanent_address_state'] 				= $mcq_val[3]['options'][0];
						  $final_data['permanent_address_district'] 		= $freetype_val[6]['value'];
						  $final_data['permanent_address_locallevel']		= $freetype_val[18]['value'];
						  $final_data['permanent_address_ward'] 				= $mcq_val[5]['options'][0];
						  	//स्थायी र हाल बसोबास भएको ठेगाना फरक छ
						  	if(in_array(1, $mcq_val[5]['options'])): 	
							  	$final_data['temp_address_state'] 				= $mcq_val[6]['options'][0];
							  	$final_data['temp_address_district'] 			= $freetype_val[9]['value'];
							  	$final_data['temp_address_locallevel'] 		= $freetype_val[19]['value'];
							  	$final_data['temp_address_ward'] 					= $freetype_val[22]['value'];
							  endif;
						  $final_data['gender'] 												= $mcq_val[1]['options'][0];
						  $final_data['disability'] 										= $mcq_val[34]['options'][0];
						  $final_data['age'] 														= $mcq_val[0]['options'][0];
					  	//If परिवार-सदस्य < 5
					  	if(in_array(5, $mcq_val[8]['options'])){
					  		$final_data['family_members_count'] 				= $mcq_val[8]['others'];
					  	}else{ //If परिवार-सदस्य > 5
					  		$final_data['family_members_count'] 				= $mcq_val[8]['options'][0];
					  	}
					  	//If हाल-देश अन्य
					  	if(in_array('others', $mcq_val[8]['options'])){
					  		$final_data['current_country_cat'] 					= $mcq_val[2]['others'];
					  	}elseif(in_array(3, $mcq_val[8]['options'])) {
					  		$final_data['current_country'] 							= $mcq_val[26]['options'][0];
					  	} 
					  	else{
					  		$final_data['current_country_cat'] 					= $mcq_val[2]['options'][0];
					  	}
						  $final_data['economi_active_family_count'] 		= $mcq_val[9]['options'][0];
						  $final_data['family_land_yes_no'] 						= $mcq_val[35]['options'][0];
						  	if(in_array(0, $mcq_val[35]['options'])){ //If परिवारको-नाममा-घर जग्गा  छ
						  		$final_data['family_land'] 								= $mcq_val[27]['options'][0];
						  	}
					  //Second Page of Form
						  $final_data['foreign_employment_year'] 				= $mcq_val[13]['options'][0];
						  $final_data['employment_type'] 								= $mcq_val[14]['others'];
						  if(in_array('others',$mcq_val[14]['options'])){ //If अन्य	 काम
						  	$final_data['employment_type_other'] = $mcq_val[14]['options'][0];
						  }
						  $final_data['skill_experience'] =[ ] ;
								$ज्याला_मज्दूरी 				= $freetype_val[28]['rows'][1][0];
								$इलेक्ट्रिशियन 				= $freetype_val[28]['rows'][2][0];
								$ड्राइभीङ्ग	 					= $freetype_val[28]['rows'][3][0];
								$सरसफाइ	 						= $freetype_val[28]['rows'][4][0];
								$ज्यामी_लेबर	 					= $freetype_val[28]['rows'][5][0];
								$कर्पोरेट_काम					= $freetype_val[28]['rows'][6][0];
								$सिलाई_बूनाइ	 				= $freetype_val[28]['rows'][7][0];
								$प्लम्बिंग	 						= $freetype_val[28]['rows'][8][0];
								$रेस्टुरेन्ट_होटल				= $freetype_val[28]['rows'][9][0];
								$उद्यम_गर्न_सक्ने			= $freetype_val[28]['rows'][10][0];
								$पेट्रोल_पम्प_सम्बन्धी		= $freetype_val[28]['rows'][11][0];
								$ईन्जिनियर						= $freetype_val[28]['rows'][12][0];
								$आइ_टील							= $freetype_val[28]['rows'][13][0];
								$स्वास्थ्य							= $freetype_val[28]['rows'][16][0];
								$पर्यटन							= $freetype_val[28]['rows'][17][0];
								$शिक्षा							= $freetype_val[28]['rows'][18][0];
								$अन्य_केही						= $freetype_val[28]['rows'][15][0];
						  if(!empty($ज्याला_मज्दूरी)){
						  	$final_data['skill_experience']['ज्याला-मज्दूरी'] 	= $ज्याला_मज्दूरी ;
						  }
						  if(!empty($इलेक्ट्रिशियन)){
						  	$final_data['skill_experience']['इलेक्ट्रिशियन'] 	= $इलेक्ट्रिशियन ;
						  }
						  if(!empty($ड्राइभीङ्ग)){
						  	$final_data['skill_experience']['ड्राइभीङ्ग'] 		= $ड्राइभीङ्ग;
						  }
						  if(!empty($सरसफाइ)){
						  	$final_data['skill_experience']['सरसफाइ']			 	= $सरसफाइ;
						  }
						  if(!empty($ज्यामी_लेबर)){
						  	$final_data['skill_experience']['ज्यामी_लेबर'] 		= $ज्यामी_लेबर;
						  }
						  if(!empty($कर्पोरेट_काम)){
						  	$final_data['skill_experience']['कर्पोरेट_काम'] 	= $कर्पोरेट_काम;
						  }
						  if(!empty($सिलाई_बूनाइ)){
						  	$final_data['skill_experience']['सिलाई_बूनाइ'] 		= $सिलाई_बूनाइ;
						  }
						  if(!empty($प्लम्बिंग)){
						  	$final_data['skill_experience']['प्लम्बिंग'] 			= $प्लम्बिंग;
						  }
						  if(!empty($रेस्टुरेन्ट_होटल)){
						  	$final_data['skill_experience']['रेस्टुरेन्ट_होटल'] 	= $रेस्टुरेन्ट_होटल;
						  }
						  if(!empty($उद्यम_गर्न_सक्ने)){
						  	$final_data['skill_experience']['उद्यम_गर्न_सक्ने'] = $उद्यम_गर्न_सक्ने;
						  }
						  if(!empty($पेट्रोल_पम्प_सम्बन्धी)){
						  	$final_data['skill_experience']['पेट्रोल_पम्प_सम्बन्धी'] = $पेट्रोल_पम्प_सम्बन्धी;
						  }
						  if(!empty($उद्यम_गर्न_सक्ने)){
						  	$final_data['skill_experience']['उद्यम_गर्न_सक्ने'] = $उद्यम_गर्न_सक्ने;
						  }
						  if(!empty($उद्यम_गर्न_सक्ने)){
						  	$final_data['skill_experience']['उद्यम_गर्न_सक्ने'] = $उद्यम_गर्न_सक्ने;
						  }
						  if(!empty($ईन्जिनियर)){
						  	$final_data['skill_experience']['ईन्जिनियर'] = $ईन्जिनियर;
						  }
						  if(!empty($आइ_टील)){
						  	$final_data['skill_experience']['आइ_टील'] = $आइ_टील;
						  }
						  if(!empty($स्वास्थ्य)){
						  	$final_data['skill_experience']['स्वास्थ्य'] = $स्वास्थ्य;
						  }
						  if(!empty($पर्यटन)){
						  	$final_data['skill_experience']['पर्यटन'] = $पर्यटन;
						  }
						  if(!empty($शिक्षा)){
						  	$final_data['skill_experience']['शिक्षा'] = $शिक्षा;
						  }
						  if(!empty($अन्य_केही)){
						  	$final_data['skill_experience']['अन्य_केही'] = $अन्य_केही;
						  }
							$final_data['covid_impact_yes_no'] 	  				= $mcq_val[15]['options'][0];
							if(in_array(0,$mcq_val[15]['options'])){
								//If छ
								$final_data['covid_imapct']									=	$mcq_val[29]['options'];
								if(in_array('others',$mcq_val[29]['options'])){ //अन्य
									$final_data['covid_imapct_other']					=	$mcq_val[29]['others'];
								}
							}
							$final_data['return_nepal'] 	 								= $mcq_val[16]['options'][0];
							if(in_array(0,$mcq_val[16]['options'])){ //If नेपाल-फर्कन
									$final_data['return_duration']  					= $mcq_val[36]['options'][0];
							}elseif(in_array(1,$mcq_val[16]['options'])){
								$final_data['no_return_reason']  						= $mcq_val[18]['options'][0];
								if(in_array('others',$mcq_val[18]['options'][0])){
									$final_data['no_return_reason_other']  		= $mcq_val[18]['others'];
								}
							}else{
								$final_data['return_duration']  						= $mcq_val[36]['options'][0];
							}
							$final_data['req_skills_list'] 								= $mcq_val[17]['options'];
							$final_data['extra_skill'] 										= $freetype_val[13]['value'];
							$final_data['extra_skill_experience'] 				= $mcq_val[41]['value'];
							$final_data['objective'] 			 								= $mcq_val[21]['options'];
							if(in_array('others',$mcq_val[21]['options'])){//If अन्य 	गर्न-खोज्दै							
									$final_data['objective_other'] 						= $mcq_val[21]['others'];
							}
							$final_data['field_of_interest'] 							= $mcq_val[33]['options'][0];
							if(in_array(0,$mcq_val[33]['options'])){//If कृषि
								$final_data['agri_sub_type'] 								= $mcq_val[31]['options'];
								if(in_array('others',$mcq_val[31]['options'])){ //If अन्य 
									$final_data['agri_sub_type_other'] 				= $mcq_val[31]['others'];
								}
							}elseif(in_array(1,$mcq_val[33]['options'])){//If उद्यम
								$final_data['udhyam_sub_type'] 							= $mcq_val[32]['options'];
								if(in_array('others',$mcq_val[31]['options'])){ //If अन्य 
									$final_data['udhyam_sub_type_other'] 			= $mcq_val[32]['others'];
								}	
							}
					 	//Third Page of Form
					 		$final_data['monthly_salary'] 							= $freetype_val[25]['value'];
					 		$final_data['insurance_pol'] 								= $mcq_val[40]['rows'][0][0];
					 		$final_data['leave_pol'] 										= $mcq_val[40]['rows'][1][0];
					 		$final_data['pariwarik_briti'] 							= $mcq_val[40]['rows'][2][0];
					 		$final_data['manufacture_pol'] 							= $mcq_val[40]['rows'][3][0];
					 		$final_data['pension_pol'] 									= $mcq_val[40]['rows'][4][0];
					 		$final_data['UNDP_involve'] 								= $mcq_val[22]['options'][0];
					 		$final_data['alternate_suggestion'] 				= $freetype_val[15]['value'];
					 		$final_data['suggestion'] 									= $freetype_val[16]['value'];
					 		$final_data['motivation_story'] 						= $freetype_val[20]['value'];
					 	array_push($final_data_array,$final_data);
					}//endforeach
				//POST the data to https://dash.sipshala.com/api/individuals 
					$new_url = 'https://dash.sipshala.com/api/individuals';
					$authorization = 'Bearer '.$decoded->token;
					$headers = ['Authorization' => $authorization];
					$new_data = array(
						'method' => 'POST',
						'timeout' => 30,
						'redirection' => 5,
						'httpversion' => '1.0',
						'blocking' => true,
						'headers' => $headers,
						'body' =>	$final_data_array,
						'cookies' => array()
					);
					$response_new = wp_remote_post($new_url, $new_data);
			endif;
	}//function send_data() closing

//Activation
register_activation_hook( __FILE__,'activation');

//Deactication
register_deactivation_hook( __FILE__,'deactivation');

