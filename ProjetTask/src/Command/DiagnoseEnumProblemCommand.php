<?php
// src/Command/DiagnoseEnumProblemCommand.php
namespace App\Command;

use App\Enum\UserRole;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:diagnose-enum-problem',
    description: 'Diagnose problems with enum values in the database',
)]
class DiagnoseEnumProblemCommand extends Command
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 1. Récupérer les valeurs actuelles dans la base de données
        $stmt = $this->connection->prepare("SELECT DISTINCT role FROM user");
        $result = $stmt->executeQuery();
        $dbValues = $result->fetchFirstColumn();

        // 2. Récupérer les valeurs définies dans l'enum
        $enumValues = array_map(fn($case) => $case->value, UserRole::cases());

        // 3. Comparer et trouver les incohérences
        $inconsistencies = array_diff($dbValues, $enumValues);

        if (empty($inconsistencies)) {
            $io->success('No inconsistencies found between database values and enum values.');
            return Command::SUCCESS;
        }

        $io->warning('Found inconsistencies between database values and enum values:');
        $io->table(
            ['Database Value', 'Available Enum Values'],
            array_map(fn($val) => [$val, implode(', ', $enumValues)], $inconsistencies)
        );

        // 4. Proposer des solutions
        $io->section('Possible solutions:');
        $io->writeln('1. Update the enum to include these values:');
        foreach ($inconsistencies as $value) {
            $enumName = strtoupper(str_replace('ROLE_', '', $value));
            $io->writeln("case {$enumName} = '{$value}';");
        }

        $io->writeln("\n2. Or update the database values to match existing enum values:");
        foreach ($inconsistencies as $dbValue) {
            $bestMatch = $this->findBestMatch($dbValue, $enumValues);
            $io->writeln("UPDATE user SET role = '{$bestMatch}' WHERE role = '{$dbValue}';");
        }

        // 5. Demander quelle solution appliquer
        if ($io->confirm('Would you like to update the database values to match the enum? (recommended)', true)) {
            foreach ($inconsistencies as $dbValue) {
                $bestMatch = $this->findBestMatch($dbValue, $enumValues);
                $stmt = $this->connection->prepare("UPDATE user SET role = ? WHERE role = ?");
                $result = $stmt->executeStatement([$bestMatch, $dbValue]);
                $io->writeln("Updated {$result} records: {$dbValue} -> {$bestMatch}");
            }
            $io->success('Database updated successfully.');
        } else {
            $io->note('No changes were made. Please update your enum or database manually.');
        }

        return Command::SUCCESS;
    }

    private function findBestMatch(string $dbValue, array $enumValues): string
    {
        // Trouver la valeur d'enum la plus proche
        $bestMatch = null;
        $bestScore = 0;

        foreach ($enumValues as $enumValue) {
            $score = similar_text($dbValue, $enumValue);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $enumValue;
            }
        }

        return $bestMatch;
    }
}
