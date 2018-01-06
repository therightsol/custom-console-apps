<?php
	
	namespace Commands;
	
	
	use Symfony\Component\Console\Command\Command;
	use Symfony\Component\Console\Input\InputArgument;
	use Symfony\Component\Console\Input\InputInterface;
	use Symfony\Component\Console\Input\InputOption;
	use Symfony\Component\Console\Output\OutputInterface;
	
	class SayHello extends Command {
		public function configure() {
			
			$this->setName( "sayHelloTo" )
			     ->setDescription( "Greeting to someone" )
			     ->addArgument( "name", InputArgument::REQUIRED, "name of the person" )
			     ->addOption( "greeting", "g", InputOption::VALUE_OPTIONAL, "change greeting text", "Hello" );
			
		}
		
		public function execute( InputInterface $input, OutputInterface $output ) {
			
			$message = sprintf( "%s, %s", $input->getOption( 'greeting' ), $input->getArgument( 'name' ) );
			
			$output->writeln(
				"<comment>{$message}</comment>"
			);
		}
	}