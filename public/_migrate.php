<?php

declare(strict_types=1);

/*
 * Exécuteur de migrations Doctrine « one-shot » pour l'hébergement OVH
 * mutualisé (pas d'accès SSH, base MySQL joignable uniquement depuis
 * l'hébergement).
 *
 * - Appelé par le workflow de déploiement juste après l'upload FTP :
 *     GET /_migrate.php?token=<APP_SECRET de prod>
 * - Protégé par APP_SECRET (comparaison à temps constant) et limité à
 *   l'environnement prod.
 * - S'autodétruit après une migration réussie ; il est de toute façon
 *   idempotent (rejouer ne fait rien si la base est à jour).
 *
 * À terme, si un déploiement par SSH devient possible, supprimer ce fichier
 * et repasser sur `bin/console doctrine:migrations:migrate`.
 */

require dirname(__DIR__).'/vendor/autoload.php';

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Dotenv\Dotenv;

header('Content-Type: text/plain; charset=utf-8');

// Charge .env / .env.local.php (généré par « composer dump-env prod »).
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if (($_SERVER['APP_ENV'] ?? null) !== 'prod') {
    http_response_code(403);
    exit("MIGRATION_FAILED: environnement non-prod\n");
}

$expected = (string) ($_SERVER['APP_SECRET'] ?? '');
$given = (string) ($_GET['token'] ?? '');

if ('' === $expected || !hash_equals($expected, $given)) {
    http_response_code(403);
    exit("MIGRATION_FAILED: jeton invalide\n");
}

$kernel = new Kernel('prod', false);
$kernel->boot();

$application = new Application($kernel);
$application->setAutoExit(false);

$output = new BufferedOutput();
$exitCode = $application->run(new ArrayInput([
    'command' => 'doctrine:migrations:migrate',
    '--no-interaction' => true,
    '--allow-no-migration' => true,
]), $output);

echo $output->fetch();

if (0 === $exitCode) {
    @unlink(__FILE__);
    echo "\nMIGRATION_OK\n";
} else {
    http_response_code(500);
    echo "\nMIGRATION_FAILED: code de sortie {$exitCode}\n";
}
