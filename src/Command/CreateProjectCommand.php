<?php

namespace Hedron\CLI\Command;

use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

class CreateProjectCommand extends HedronCommand {

  protected function configure() {
    $this->setName('project:create')
      ->setDescription('Create a new project')
      ->addArgument('server', InputArgument::OPTIONAL, 'The server on which this project should be created.', 'local');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $user_directory = trim(shell_exec("cd ~; pwd"));
    $this->setHedronDir($user_directory . DIRECTORY_SEPARATOR . '.hedron');
    $file = $this->getHedronDir('hedron.yml');
    $helper = $this->getHelper('question');
    $question = new Question('Client: ', '');
    $question->setValidator([$this, 'validateClient']);
    $yaml = Yaml::parse(file_get_contents($file));
    $question->setAutocompleterValues($yaml['client']);
    $client = $helper->ask($input, $output, $question);
    // @todo validate project name
    $question = new Question('Project Name: ', '');
    $projectName = $helper->ask($input, $output, $question);
    // @todo validate project type
    $question = new Question('Project Type: ', '');
    $projectType = $helper->ask($input, $output, $question);
    $server = $input->getArgument('server');

    $project_dir = strtolower($client) . DIRECTORY_SEPARATOR . strtolower($projectName);

    $environment = [];
    $environment['client'] = $client;
    $environment['name'] = $projectName;
    $environment['projectType'] = $projectType;
    $environment['host'] = $server;
    $environment['gitDirectory'] = $this->getHedronDir('working_dir', $project_dir);
    if (mkdir($this->getHedronDir('working_dir', $project_dir), 0777, TRUE)) {
      $output->writeln('<info>Project working directory successfully created.</info>');
    }
    $environment['gitRepository'] = $this->getHedronDir('repositories', $project_dir);
    if (mkdir($this->getHedronDir('repositories', $project_dir), 0777, TRUE)) {
      $output->writeln('<info>Project repository directory successfully created.</info>');
    }
    $environment['dockerDirectory'] = $this->getHedronDir('docker', $project_dir);
    if (mkdir($this->getHedronDir('docker', $project_dir), 0777, TRUE)) {
      $output->writeln('<info>Project docker directory successfully created.</info>');
    }
    $environment['dataDirectory'] = $this->getHedronDir('data', $project_dir, '{branch}', 'web');
    if (mkdir($this->getHedronDir('data', $project_dir), 0777, TRUE)) {
      $output->writeln('<info>Project data directory successfully created.</info>');
    }
    if (mkdir($this->getHedronDir('data', $project_dir, 'master', 'web'), 0777, TRUE)) {
      $output->writeln('<info>Project data web directory successfully created.</info>');
    }
    if (mkdir($this->getHedronDir('data', $project_dir, 'master', 'sql'), 0777, TRUE)) {
      $output->writeln('<info>Project data sql directory successfully created.</info>');
    }

    // Make the project.
    $dir = $this->getHedronDir('project', $project_dir);
    if (mkdir($dir, 0777, TRUE)) {
      $environment_file = $this->getHedronDir('project', $project_dir, 'environment.yml');
      file_put_contents($environment_file, Yaml::dump($environment, 10));
    }

    // Make the repository.
    $dir = $this->getHedronDir('repositories', $project_dir);
    $commands = [];
    $commands[] = "cd $dir";
    $commands[] = "git init --bare";
    shell_exec(implode('; ', $commands));
    unset($commands);
    if (file_exists($dir . DIRECTORY_SEPARATOR . 'hooks')) {
      $hedron_hooks = $this->getHedronDir('hedron', 'hooks');
      $git_hooks_dir = $dir . DIRECTORY_SEPARATOR . 'hooks';
      foreach (array_diff(scandir($hedron_hooks), array('..', '.')) as $file_name) {
        $file = "#!{$yaml['php']}\n";
        $file .= file_get_contents($hedron_hooks . DIRECTORY_SEPARATOR . $file_name);
        file_put_contents($git_hooks_dir . DIRECTORY_SEPARATOR . $file_name, $file);
        $file_name = $git_hooks_dir . DIRECTORY_SEPARATOR . $file_name;
        shell_exec("chmod a+x $file_name");
      }
      $output->writeln("<info>To begin working:\n
git clone $dir\n
OR in the directory you wish to push to this repository:\n
git init\n
git remote add origin $dir\n
git push -u origin master\n
\n
Your website volume for docker-compose.yml configuration is: {$this->getHedronDir('data', $project_dir, 'web')}\n
Your sql volume for docker-compose.yml configuration is: {$this->getHedronDir('data', $project_dir, 'sql')}</info>");
    }
  }

  public function validateClient($answer) {
    $file = $this->getHedronDir('hedron.yml');
    $yaml = Yaml::parse(file_get_contents($file));
    if (!in_array($answer, $yaml['client'])) {
      throw new RuntimeException("The selected client does not exists, run the client:create command to add the client first.");
    }
    return $answer;
  }
}