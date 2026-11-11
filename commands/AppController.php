<?php

declare(strict_types=1);

namespace app\commands;

use app\models\User;
use app\services\DataReset;
use app\services\DemoDataLoader;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

class AppController extends Controller
{
    public bool $force = false;

    /**
     * @param string $actionID
     * @return string[]
     */
    public function options($actionID)
    {
        return $actionID === 'reset-data' ? ['force'] : [];
    }

    public function actionInitAdmin(): int
    {
        $email = mb_strtolower(trim((string) Yii::$app->params['adminEmail']));
        $password = (string) (getenv('APP_ADMIN_PASSWORD') ?: '');

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->stderr("Set APP_ADMIN_EMAIL to a valid email address.\n");

            return ExitCode::USAGE;
        }

        if (strlen($password) < 10 || strlen($password) > 200) {
            $this->stderr("Set APP_ADMIN_PASSWORD to 10–200 characters. It is never echoed.\n");

            return ExitCode::USAGE;
        }

        $user = User::findByEmail($email);
        $created = false;
        if ($user === null) {
            $user = new User(['email' => $email]);
            $created = true;
        }

        $user->setPassword($password);
        if (!$user->save()) {
            $this->stderr("Unable to save the admin user.\n");

            return ExitCode::SOFTWARE;
        }

        $this->stdout(sprintf("%s admin user: %s\n", $created ? 'Created' : 'Updated', $email));

        return ExitCode::OK;
    }

    public function actionDemoData(): int
    {
        $result = (new DemoDataLoader())->load(null);

        $this->stdout(sprintf(
            "Demo imports loaded: %d, skipped: %d, keywords: %d.\n",
            $result['loaded'],
            $result['skipped'],
            $result['records'],
        ));

        foreach ($result['errors'] as $error) {
            $this->stderr($error . "\n");
        }

        return $result['errors'] === [] ? ExitCode::OK : ExitCode::SOFTWARE;
    }

    public function actionResetData(): int
    {
        if (!$this->force) {
            $this->stderr("This deletes all imports, keywords and campaigns. Re-run with --force.\n");

            return ExitCode::USAGE;
        }

        $removed = (new DataReset())->run();

        $this->stdout(sprintf(
            "Removed imports: %d, keywords: %d, campaigns: %d. Settings and admin user kept.\n",
            $removed['imports'],
            $removed['keywords'],
            $removed['campaigns'],
        ));

        return ExitCode::OK;
    }
}
