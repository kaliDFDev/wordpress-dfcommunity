<?php

if ( !defined('ABSPATH') ){ die(); } //Exit if accessed directly

trait Companion_Functions {
	public function hooks(){
		global $pagenow;

		add_action('nebula_ga_before_send_pageview', array($this, 'poi_custom_dimension'));
		add_filter('nebula_hubspot_identify', array($this, 'poi_hubspot'), 10, 1);
		add_filter('nebula_cf7_debug_data', array($this, 'poi_cf7_debug_info'), 10, 1);

		add_filter('nebula_measurement_protocol_custom_definitions', array($this, 'poi_measurement_protocol'), 10, 1);

		add_filter('nebula_warnings', array($this, 'nebula_companion_warnings'));
	}






	public function poi_custom_dimension(){
		//Notable POI (IP Addresses)
		$poi = $this->poi();
		if ( nebula()->get_option('cd_notablepoi') && !empty($poi) ){
			echo 'ga("set", nebula.analytics.dimensions.poi, "' . esc_html($poi) . '");';
		}
	}

	public function poi_hubspot($hubspot_identify){
		$hubspot_identify['notable_poi'] = $this->poi();
		return $hubspot_identify;
	}

	public function poi_measurement_protocol($common_parameters){
		if ( nebula()->get_option('cd_notablepoi') ){
			$common_parameters['cd' . nebula()->ga_definition_index(nebula()->get_option('cd_notablepoi'))] = $this->poi();
		}

		return $common_parameters;
	}

	public function poi_cf7_debug_info($debug_data){
		$notable_poi = $this->poi();
		if ( !empty($notable_poi) ){
			$debug_data .= $notable_poi . PHP_EOL;
		}

		return $debug_data;
	}



	public function is_auditing(){
		if ( nebula()->get_option('audit_mode') || (isset($_GET['audit']) && nebula()->is_dev()) ){
			return true;
		}

		return false;
	}

	//Add more warnings to the Nebula check
	public function nebula_companion_warnings($nebula_warnings){
		nebula()->timer('Nebula Companion Warnings');

		//If Audit Mode is enabled
		if ( $this->is_auditing() ){
			$nebula_audit_mode_expiration = get_transient('nebula_audit_mode_expiration');
			if ( empty($nebula_audit_mode_expiration) ){
				$nebula_audit_mode_expiration = time();
			}

			if ( nebula()->get_option('audit_mode') ){
				$nebula_warnings[] = array(
					'category' => 'Nebula Companion',
					'level' => 'error',
					'description' => '<i class="fas fa-fw fa-microscope"></i> <a href="themes.php?page=nebula_options&tab=advanced&option=audit_mode">Audit Mode</a> is enabled! This is visible to all visitors. It will automatically be disabled in ' . human_time_diff($nebula_audit_mode_expiration+HOUR_IN_SECONDS) . '.',
					'url' => get_admin_url() . 'themes.php?page=nebula_options&tab=advanced&option=audit_mode'
				);
			}
		}

		//Audit mode only warnings (not used on one-off page audits)
		if ( nebula()->get_option('audit_mode') ){
			//Remind to check incognito
			if ( is_plugin_active('query-monitor/query-monitor.php') && nebula()->get_option('jquery_version') === 'footer' ){
				$nebula_warnings[] = array(
					'category' => 'Nebula Companion',
					'level' => 'warn',
					'description' => 'Plugins may move jQuery back to the head. Be sure to check incognito for JavaScript errors.',
				);
			}
		}

		//Only check these when auditing (not on all pageviews) to prevent undesired server load
		if ( $this->is_auditing() ){
			//Check contact email address
			if ( !nebula()->get_option('contact_email') ){
				$default_contact_email = get_option('admin_email', nebula()->get_user_info('user_email', array('id' => 1)));
				$email_domain = substr($default_contact_email, strpos($default_contact_email, "@")+1);
				if ( $email_domain != nebula()->url_components('domain') ){
					$nebula_warnings[] = array(
						'category' => 'Nebula Companion',
						'level' => 'warn',
						'description' => '<i class="fas fa-fw fa-address-card"></i> Default contact email domain does not match website. This email address will appear in metadata, so please verify this is acceptable.',
						'url' => get_admin_url() . 'themes.php?page=nebula_options&tab=metadata&option=contact_email'
					);
				}
			}

			//Check if readme.html exists. If so, recommend deleting it.
			if ( file_exists(get_home_path() . '/readme.html') ){
				$nebula_warnings[] = array(
					'category' => 'Nebula Companion',
					'level' => 'warn',
					'description' => '<i class="far fa-fw fa-file-alt"></i> The WordPress core readme.html file exists (which exposes version information) and should be deleted.',
				);
			}

			//Check if session directory is writable
			if ( !is_writable(session_save_path()) ){
				$nebula_warnings[] = array(
					'category' => 'Nebula Companion',
					'level' => 'warn',
					'description' => '<i class="fas fa-fw fa-server"></i> The session directory (' . session_save_path() . ') is not writable. Session data can not be used!',
				);
			}











			//Check each image within the_content()
				//If CMYK: https://stackoverflow.com/questions/8646924/how-can-i-check-if-a-given-image-is-cmyk-in-php
				//If not Progressive JPEG
				//If Quality setting is over 80%: https://stackoverflow.com/questions/2024947/is-it-possible-to-tell-the-quality-level-of-a-jpeg

			if ( 1==2 ){ //if post or page or custom post type? maybe not- just catch everything
				$post = get_post(get_the_ID());
				preg_match_all('/src="([^"]*)"/', $post->post_content, $matches); //Find images in the content... This wont work: I need the image path not url

				foreach ( $matches as $image_url ){
					//Check CMYK
					$image_info = getimagesize($image_url);
					//var_dump($image_info); echo '<br>';
					if ( $image_info['channels'] == 4 ){
						echo 'it is cmyk<br><br>'; //ADD WARNING HERE
					} else {
						echo 'it is rgb<br><br>';
					}
				}

			}











			if ( !nebula()->is_admin_page() ){ //Front-end (non-admin) page warnings only
				//Check within all child theme files for various issues
				foreach ( nebula()->glob_r(get_stylesheet_directory() . '/*') as $filepath ){
					if ( is_file($filepath) ){
						$skip_filenames = array('README.md', 'debug_log', 'error_log', '/vendor', 'resources/');
						if ( !nebula()->contains($filepath, nebula()->skip_extensions()) && !nebula()->contains($filepath, $skip_filenames) ){ //If the filename does not contain something we are ignoring
							//Prep an array of strings to look for
							if ( substr(basename($filepath), -3) == '.js' ){ //JavaScript files
								$looking_for['debug_output'] = "/console\./i";
							} elseif ( substr(basename($filepath), -4) == '.php' ){ //PHP files
								$looking_for['debug_output'] = "/var_dump\(|var_export\(|print_r\(/i";
							} elseif ( substr(basename($filepath), -5) == '.scss' ){ //Sass files
								continue; //Remove this to allow checking scss files
								$looking_for['debug_output'] = "/@debug/i";
							} else {
								continue; //Skip any other filetype
							}

							//Check for Bootstrap JS functionality if bootstrap JS is disabled
							if ( !nebula()->get_option('allow_bootstrap_js') ){
								$looking_for['bootstrap_js'] = "/\.modal\(|\.bs\.|data-toggle=|data-target=|\.dropdown\(|\.tab\(|\.tooltip\(|\.carousel\(/i";
							}

							//Search the file and output if found anything
							if ( !empty($looking_for) ){
								foreach ( file($filepath) as $line_number => $full_line ){ //Loop through each line of the file
									foreach ( $looking_for as $category => $regex ){ //Search through each string we are looking for from above
										if ( preg_match("/^\/\/|\/\*|#/", trim($full_line)) == true ){ //Skip lines that begin with a comment
											continue;
										}

										preg_match($regex, $full_line, $details); //Actually Look for the regex in the line

										if ( !empty($details) ){
											if ( $category === 'debug_output' ){
												$nebula_warnings[] = array(
													'category' => 'Nebula Companion',
													'level' => 'warn',
													'description' => '<i class="fas fa-fw fa-bug"></i> Possible debug output in <strong>' . str_replace(get_stylesheet_directory(), '', dirname($filepath)) . '/' . basename($filepath) . '</strong> on <strong>line ' . ($line_number+1) . '</strong>.'
												);
											} elseif ( $category === 'bootstrap_js' ){
												$nebula_warnings[] = array(
													'category' => 'Nebula Companion',
													'level' => 'warn',
													'description' => '<i class="fab fa-fw fa-bootstrap"></i> Bootstrap JS is disabled, but is possibly needed in <strong>' . str_replace(get_stylesheet_directory(), '', dirname($filepath)) . '/' . basename($filepath) . '</strong> on <strong>line ' . $line_number . '</strong>.',
													'url' => get_admin_url() . 'themes.php?page=nebula_options&tab=functions&option=allow_bootstrap_js'
												);
											}
										}
									}
								}
							}
						}
					}
				}
			}

			//Check for sitemap
			if ( !nebula()->is_available(home_url('/') . 'sitemap_index.xml', false, true) ){
				$nebula_warnings[] = array(
					'category' => 'Nebula Companion',
					'level' => 'warn',
					'description' => '<i class="fas fa-fw fa-sitemap"></i> Missing sitemap XML'
				);
			}

			//Check word count for SEO
			$word_count = nebula()->word_count();
			if ( $word_count < 1900 ){
				$word_count_warning = ( $word_count === 0 )? 'Word count audit is not looking for custom fields outside of the main content editor. <a href="https://gearside.com/nebula/functions/word_count/?utm_campaign=nebula&utm_medium=nebula&utm_source=' . urlencode(get_bloginfo('name')) . '&utm_content=word+count+audit+warning" target="_blank">Hook custom fields into the Nebula word count functionality</a> to properly audit.' : 'Word count (' . $word_count . ') is low for SEO purposes (Over 1,000 is good, but 1,900+ is ideal). <small>Note: Detected word count may not include custom fields!</small>';
				$nebula_warnings[] = array(
					'category' => 'Nebula Companion',
					'level' => 'warn',
					'description' => '<i class="far fa-fw fa-file"></i> ' . $word_count_warning,
					'url' => get_edit_post_link(get_the_id())
				);
			}

			//Check for Yoast active
			if ( !is_plugin_active('wordpress-seo/wp-seo.php') ){
				$nebula_warnings[] = array(
					'category' => 'Nebula Companion',
					'level' => 'warn',
					'description' => '<i class="fas fa-fw fa-search-plus"></i> Yoast SEO plugin is not active',
					'url' => get_admin_url() . 'themes.php?page=tgmpa-install-plugins'
				);
			}
		}

		//If website is live and using Prototype Mode
		if ( nebula()->is_site_live() && nebula()->get_option('prototype_mode') ){
			$nebula_warnings[] = array(
				'category' => 'Nebula Companion',
				'level' => 'warn',
				'description' => '<i class="far fa-fw fa-clone"></i> <a href="themes.php?page=nebula_options&tab=advanced&option=prototype_mode">Prototype Mode</a> is enabled (' . ucwords($this->dev_phase()) . ')!',
				'url' => get_admin_url() . 'themes.php?page=nebula_options&tab=advanced&option=prototype_mode'
			);
		}

		//If Prototype mode is disabled, but Multiple Theme plugin is still activated
		if ( !nebula()->get_option('prototype_mode') && is_plugin_active('jonradio-multiple-themes/jonradio-multiple-themes.php') ){
			$nebula_warnings[] = array(
				'category' => 'Nebula Companion',
				'level' => 'error',
				'description' => '<i class="far fa-fw fa-clone"></i> <a href="themes.php?page=nebula_options&tab=advanced&option=prototype_mode">Prototype Mode</a> is disabled, but <a href="plugins.php">Multiple Theme plugin</a> is still active.',
				'url' => get_admin_url() . 'plugins.php'
			);
		}

		if ( $this->is_auditing() ){
			$nebula_warnings = apply_filters('nebula_audits_php', $nebula_warnings);
		}

		nebula()->timer('Nebula Companion Warnings', 'end');
		return $nebula_warnings;
	}



}