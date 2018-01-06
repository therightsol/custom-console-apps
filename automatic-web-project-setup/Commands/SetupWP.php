<?php
	
	namespace Commands;
	
	
	use GuzzleHttp\ClientInterface;
	use Symfony\Component\Console\Command\Command;
	use Symfony\Component\Console\Input\InputArgument;
	use Symfony\Component\Console\Input\InputInterface;
	use Symfony\Component\Console\Output\OutputInterface;
	use Symfony\Component\Console\Question\Question;
	
	class SetupWP extends Command {
		
		private $client;
		private $hosts_file_path;
		private $vhosts_file_path;
		private $domainName;
		private $htdocs_www_directory;
		private $temp_dir_path;
		private $temp_hosts_file;
		private $temp_vhosts_file;
		private $step = 1;
		
		public function __construct( ClientInterface $client ) {
			$this->client = $client;
			
			$this->temp_dir_path    = dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'temp';
			$this->temp_hosts_file  = $this->temp_dir_path . DIRECTORY_SEPARATOR . 'hosts';
			$this->temp_vhosts_file = $this->temp_dir_path . DIRECTORY_SEPARATOR . 'httpd-vhosts.conf';
			
			parent::__construct();
		}
		
		public function configure() {
			$this->setName( 'setup-wp' )
			     ->setDescription( "Download the latest WordPress file, update hosts and vhosts file" )
			     ->addArgument( 'directory', InputArgument::REQUIRED, "Add directory name" );
		}
		
		public function execute( InputInterface $input, OutputInterface $output ) {
			
			$applicationDirectoryName = $input->getArgument( 'directory' );
			$directory                = getcwd() . DIRECTORY_SEPARATOR . $applicationDirectoryName;
			
			$this->assertApplicationDoesNotExist( $directory, $output )
			     ->assertHostsFileExists( $input, $output )
			     ->assertVHostsFilesExist( $input, $output )
			     ->assertHTDOCSFileExist( $input, $output )
			     ->getDomainName( $input, $output )
				 ->assertDirectoryNotExists($output)
			     ->backingUpHostsFile( $output )
			     ->assertCheckDomainNotExistInHostsFile( $output )
			     ->backingUpVhostsFile( $output )
			     ->assertDomainNotExistInVHostsFile( $output )
			     ->download_wp( $zipFileName = $this->makeFileName(), $output )
			     ->extract_zip( $zipFileName, $output )
			     ->move_files( $output )
			     ->updatingHostsFile( $output )
			     ->updatingVHostsFile( $output )
			     ->cleanUp( $zipFileName, $directory, $output );
			
			
		}
		
		private function makeFileName() {
			return getcwd() . "/wordpress-" . md5( time() . uniqid() ) . '.zip';
		}
		
		private function assertApplicationDoesNotExist( $directory, OutputInterface $output ) {
			$output->writeln( "" );
			$output->write( "<comment>Step $this->step : Checking if application exists.....</comment>" );
			
			if ( is_dir( $directory ) ) {
				$output->writeln( "<error>Application already exists</error>" );
				
				$output->writeln( "<error>Failed.</error>" );
				$output->writeln( '' );
				exit( 1 );
			}
			
			$output->write( "<info>Successful!!!!!</info>" );
			
			$this->step ++;
			
			return $this;
		}
		
		private function assertHostsFileExists( InputInterface $input, OutputInterface $output ) {
			$output->writeln( "" );
			$output->write( "<comment>Step $this->step : Checking Host file.....</comment>" );
			
			$hosts_file_path      = '';
			$hosts_info_file      = 'hosts.f';
			$hosts_info_file_path = __DIR__ . DIRECTORY_SEPARATOR . $hosts_info_file;
			
			if ( file_exists( $hosts_info_file_path ) ) {
				$hosts_file_path_base64 = file_get_contents( $hosts_info_file_path );
				$hosts_file_path        = base64_decode( $hosts_file_path_base64 );
			} else {
				$handle = fopen( $hosts_info_file_path, 'w' );
				fclose( $handle );
			}
			
			$output->writeln( "" );
			$helper   = $this->getHelper( 'question' );
			$question = new Question( 'Please enter Hosts file path [' . $hosts_file_path . '] : ', $hosts_file_path );
			
			
			$i = 0;
			do {
				
				if ( $i >= 5 ) {
					$output->writeln( "<error>Hosts file path is wrong. Too many bad tries.</error>" );
					exit( 1 );
				}
				
				
				if ( ! file_exists( $hosts_file_path ) && $i > 0 ) {
					$output->writeln( "<error>Hosts file path '" . $hosts_file_path . "' is not correct.</error>" );
				}
				
				$hosts_file_path = $helper->ask( $input, $output, $question );
				$i ++;
			} while ( empty( $hosts_file_path ) || ! file_exists( $hosts_file_path ) );
			
			$this->hosts_file_path = str_replace( '\\', '/', $hosts_file_path );
			
			file_put_contents( $hosts_info_file_path, base64_encode( $hosts_file_path ) );
			
			$output->write( "<info>Successful!!!!!</info>" );
			
			
			$this->step ++;
			
			return $this;
			
		}
		
		private function assertVHostsFilesExist( InputInterface $input, OutputInterface $output ) {
			$output->writeln( "" );
			$output->write( "<comment>Step $this->step : Checking httpd-vhosts.conf file.....</comment>" );
			
			$vhosts_file_path      = '';
			$vhosts_info_file      = 'vhosts.f';
			$vhosts_info_file_path = __DIR__ . DIRECTORY_SEPARATOR . $vhosts_info_file;
			
			if ( file_exists( $vhosts_info_file_path ) ) {
				$vhosts_file_path_base64 = file_get_contents( $vhosts_info_file_path );
				$vhosts_file_path        = base64_decode( $vhosts_file_path_base64 );
			} else {
				$handle = fopen( $vhosts_info_file_path, 'w' );
				fclose( $handle );
			}
			
			$output->writeln( "" );
			$helper   = $this->getHelper( 'question' );
			$question = new Question( 'Please enter httpd-vhosts.conf file path [' . $vhosts_file_path . '] : ', $vhosts_file_path );
			
			$i = 0;
			do {
				
				if ( $i >= 5 ) {
					$output->writeln( "<error>V-Hosts file path is wrong. Too many bad tries.</error>" );
					exit( 1 );
				}
				
				
				if ( ! file_exists( $vhosts_file_path ) && $i > 0 ) {
					$output->writeln( "<error>V-Hosts file path '" . $vhosts_file_path . "' is not correct.</error>" );
				}
				
				$vhosts_file_path = $helper->ask( $input, $output, $question );
				$i ++;
			} while ( empty( $vhosts_file_path ) || ! file_exists( $vhosts_file_path ) );
			
			$this->vhosts_file_path = str_replace( '\\', '/', $vhosts_file_path );
			
			file_put_contents( $vhosts_info_file_path, base64_encode( $vhosts_file_path ) );
			
			$output->write( "<info>Successful!!!!!</info>" );
			
			$this->step ++;
			
			return $this;
			
		}
		
		private function assertHTDOCSFileExist( InputInterface $input, OutputInterface $output ) {
			$output->writeln( "" );
			$output->write( "<comment>Step $this->step : Checking htdocs/www file.....</comment>" );
			
			$htdocs_directory_path      = '';
			$htdocs_info_file           = 'htdocs.f';
			$htdocs_info_directory_path = __DIR__ . DIRECTORY_SEPARATOR . $htdocs_info_file;
			
			if ( file_exists( $htdocs_info_directory_path ) ) {
				$htdocs_file_path_base64 = file_get_contents( $htdocs_info_directory_path );
				$htdocs_directory_path   = base64_decode( $htdocs_file_path_base64 );
			} else {
				$handle = fopen( $htdocs_info_directory_path, 'w' );
				fclose( $handle );
			}
			
			$output->writeln( "" );
			$helper   = $this->getHelper( 'question' );
			$question = new Question( 'Please enter htdocs/www folder path [' . $htdocs_directory_path . '] : ', $htdocs_directory_path );
			
			$i = 0;
			do {
				
				if ( $i >= 5 ) {
					$output->writeln( "<error>Htdocs/www directory path is wrong. Too many bad tries.</error>" );
					exit( 1 );
				}
				
				
				if ( ! file_exists( $htdocs_directory_path ) && $i > 0 ) {
					$output->writeln( "<error>Htdocs/www directory path '" . $htdocs_directory_path . "' is not correct.</error>" );
				}
				
				$htdocs_directory_path = $helper->ask( $input, $output, $question );
				$i ++;
			} while ( empty( $htdocs_directory_path ) || ! file_exists( $htdocs_directory_path ) );
			
			$this->htdocs_www_directory = str_replace( '\\', '/', $htdocs_directory_path );
			
			file_put_contents( $htdocs_info_directory_path, base64_encode( $htdocs_directory_path ) );
			
			$output->write( "<info>Successful!!!!!</info>" );
			
			$this->step ++;
			
			return $this;
		}
		
		private function getDomainName( InputInterface $input, OutputInterface $output ) {
			$output->writeln( "" );
			$output->write( "<comment>Step $this->step : Getting Domain Name .....</comment>" );
			
			$domainName = '';
			$i          = 0;
			
			$question = new Question( "\nPlease enter domain name:  ", $domainName );
			
			
			do {
				
				if ( $i >= 5 ) {
					$output->writeln( "<error>Wrong Domain Name. Too many bad tries.</error>" );
					exit( 1 );
				}
				
				
				if ( empty( $domainName ) && $i > 0 ) {
					$output->writeln( "<error>Domain name '" . $domainName . "' should not be empty.</error>" );
				}
				$helper     = $this->getHelper( 'question' );
				$domainName = $helper->ask( $input, $output, $question );
				$i ++;
			} while ( empty( $domainName ) || ! preg_match( "/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,7}$/", $domainName ) );
			
			$this->domainName = strtolower( $domainName );
			
			
			$output->write( "<info>Successful!!!!!</info>" );
			
			$this->step ++;
			
			return $this;
		}
		
		private function assertDirectoryNotExists( OutputInterface $output ) {
			$output->writeln( "" );
			$output->write( "<comment>Step $this->step : Checking htdocs/www for directory .....</comment>" );
			
			if (file_exists($this->htdocs_www_directory . DIRECTORY_SEPARATOR . $this->domainName)){
				$output->writeln("<error>Sorry! Directory with same name '$this->domainName' is exists in $this->htdocs_www_directory");
				exit(1);
			}
			
			
			$output->write( "<info>Successful!!!!!</info>" );
			$this->step ++;
			return $this;
		}
		
		private function backingUpHostsFile( OutputInterface $output ) {
			$output->writeln( "" );
			$output->write( "<comment>Step $this->step : Backing Up Hosts File.....</comment>" );
			
			// backing up host file into temp folder
			if ( ! file_exists( $this->temp_dir_path ) ) {
				mkdir( $this->temp_dir_path );
			}
			shell_exec( "cp $this->hosts_file_path $this->temp_hosts_file" );
			
			
			$output->write( "<info>Successful!!!!!</info>" );
			$this->step ++;
			
			return $this;
		}
		
		private function assertCheckDomainNotExistInHostsFile( OutputInterface $output ) {
			$output->writeln( "" );
			$output->write( "<comment>Step $this->step : Checking duplicate domains.....</comment>" );
			
			// Check if entry exists in Hosts file
			$hosts_content = file_get_contents( $this->temp_hosts_file );
			if ( strpos( $hosts_content, $this->domainName ) !== false ) {
				$output->writeln( "<error>Sorry! Domain with same in in Hosts file is already exists.</error>" );
				$output->writeln( "<error>Exiting...</error>" );
				exit( 1 );
			}
			
			
			$output->write( "<info>Successful!!!!!</info>" );
			$this->step ++;
			
			return $this;
			
		}
		
		private function backingUpVhostsFile( OutputInterface $output ) {
			$output->writeln( "" );
			$output->write( "<comment>Step $this->step : Backing up httpd-vhosts.conf.....</comment>" );
			
			// backing up httpd-vhosts.conf file into temp folder
			if ( ! file_exists( $this->temp_dir_path ) ) {
				mkdir( $this->temp_dir_path );
			}
			shell_exec( "cp $this->vhosts_file_path $this->temp_vhosts_file" );
			
			$output->write( "<info>Successful!!!!!</info>" );
			$this->step ++;
			
			return $this;
		}
		
		private function assertDomainNotExistInVHostsFile( OutputInterface $output ) {
			$output->writeln( "" );
			$output->write( "<comment>Step $this->step : Checking if entry exists in httpd-vhosts.conf.....</comment>" );
			
			
			// Check if entry exists in Hosts file
			$vhosts_content = file_get_contents( $this->temp_vhosts_file );
			if ( strpos( $vhosts_content, $this->domainName ) !== false ) {
				$output->writeln( "<error>Sorry! Domain with same in in httpd-vhosts.conf file is already exists.</error>" );
				$output->writeln( "<error>Exiting...</error>" );
				exit( 1 );
			}
			
			
			$output->write( "<info>Successful!!!!!</info>" );
			$this->step ++;
			
			return $this;
		}
		
		
		private function download_wp( $zipFileName, OutputInterface $output ) {
			$output->writeln( "" );
			$output->write( "<comment>Step $this->step : Downloading Latest WordPress.....</comment>" );
			
			$response = $this->client->get( "https://wordpress.org/latest.zip" )->getBody();
			file_put_contents( $zipFileName, $response );
			
			$output->write( "<info>Successful!!!!!</info>" );
			$this->step ++;
			
			return $this;
		}
		
		private function extract_zip( $zipFileName, OutputInterface $output ) {
			$output->writeln( "" );
			$output->write( "<comment>Step $this->step : Extracting WordPress File.....</comment>" );
			
			if (! file_exists($this->htdocs_www_directory . DIRECTORY_SEPARATOR . $this->domainName)){
				@mkdir($this->htdocs_www_directory . DIRECTORY_SEPARATOR . $this->domainName);
			}
			
			$archive = new \ZipArchive();
			$archive->open( $zipFileName );
			$archive->extractTo( $this->htdocs_www_directory . DIRECTORY_SEPARATOR . $this->domainName );
			$archive->close();
			
			$output->write( "<info>Successful!!!!!</info>" );
			
			$this->step ++;
			
			return $this;
		}
		
		private function move_files( OutputInterface $output ) {
			$output->writeln( '' );
			$output->write( "<comment>Step $this->step : File moving started.....</comment>" );
			
			
			$dir    = $this->htdocs_www_directory . DIRECTORY_SEPARATOR . $this->domainName . DIRECTORY_SEPARATOR . 'wordpress';//"path/to/targetFiles";
			$dirNew = $this->htdocs_www_directory . DIRECTORY_SEPARATOR . $this->domainName; //path/to/destination/files
			
			// Open a known directory, and proceed to read its contents
			if ( is_dir( $dir ) ) {
				if ( $dh = opendir( $dir ) ) {
					while ( ( $file = readdir( $dh ) ) !== false ) {
						
						if ( $file == "." ) {
							continue;
						}
						if ( $file == ".." ) {
							continue;
						}
						
						if ( ! rename( $dir . '/' . $file, $dirNew . '/' . $file ) ) {
							$output->writeln( "<error>File Not Moved: $file</error>" );
						}
					}
					closedir( $dh );
				}
			}
			
			$output->write( "<info>Successful!!!!!</info>" );
			
			$this->step ++;
			
			return $this;
		}
		
		private function updatingHostsFile( OutputInterface $output ) {
			$output->writeln( '' );
			$output->write( "<comment>Step $this->step : Updating Host File.....</comment>" );
			
			
			exec( " echo '\n#----------------------#Adding entry into Hosts File------------------------' >> $this->hosts_file_path" );
			exec( "echo '#DATED: " . date( "l, d-F-Y h:m:s" ) . "' >> $this->hosts_file_path" );
			exec( "echo '127.0.0.1 \t $this->domainName' >> $this->hosts_file_path" );
			exec( " echo '#------------------------#Ending entry into Hosts File----------------------\n' >> $this->hosts_file_path" );
			
			
			$output->write( "<info>Successful!!!!!</info>" );
			$this->step ++;
			
			return $this;
			
		}
		
		private function updatingVHostsFile( OutputInterface $output ) {
			$output->writeln( '' );
			$output->write( "<comment>Step $this->step : Updating httpd-vhosts.conf File.....</comment>" );
			
			$document_root = $this->htdocs_www_directory . DIRECTORY_SEPARATOR . $this->domainName;
			$document_root = str_replace('/', '\\', $document_root);
			
			exec( " echo '\n\n#--------- #Adding entry into httpd-vhosts.conf File ---------' >> $this->vhosts_file_path " );
			exec( "echo '#DATED: " . date( "l, d-F-Y h:m:s" ) . "' >> $this->vhosts_file_path" );
			exec( "echo '<VirtualHost *:80>' >> $this->vhosts_file_path" );
			exec( "echo '\tDocumentRoot \"$document_root\"' >> $this->vhosts_file_path" );
			exec( "echo '\tServerName $this->domainName' >> $this->vhosts_file_path" );
			exec( "echo '\t<Directory \"$document_root\">' >> $this->vhosts_file_path" );
			exec( "echo '\t\tOrder allow,deny' >> $this->vhosts_file_path" );
			exec( "echo '\t\tAllow from all' >> $this->vhosts_file_path" );
			exec( "echo '\t</Directory>' >> $this->vhosts_file_path" );
			exec( "echo '</VirtualHost>' >> $this->vhosts_file_path" );
			
			exec( " echo '#--------- #Ending entry into httpd-vhosts.conf File --------- \n' >> $this->vhosts_file_path" );
			
			
			$output->write( "<info>Successful!!!!!</info>" );
			$this->step ++;
			
			return $this;
		}
		
		private function cleanUp( $zipFile, $directory, OutputInterface $output ) {
			$output->writeln( '' );
			$output->write( "<comment>Step $this->step : Deleting temporary WordPress compressed file.....</comment>" );
			
			@chmod( $zipFile, 0777 );
			@unlink( $zipFile );
			
			if ( file_exists( $directory . DIRECTORY_SEPARATOR . 'wordpress' ) ) {
				@chmod( $directory . DIRECTORY_SEPARATOR . 'wordpress', 0777 );
				@rmdir( $directory . DIRECTORY_SEPARATOR . 'wordpress' );
			}
			
			$output->write( "<info>Successful!!!!!</info>" );
			$this->step ++;
			
			return $this;
		}
		
		
	}