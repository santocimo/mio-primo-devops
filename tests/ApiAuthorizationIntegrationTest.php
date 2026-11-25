<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../inc/api_token.php';

class ApiAuthorizationIntegrationTest extends TestCase
{
    private const TOKEN_SECRET = 'rbac-integration-test-signing-key-32chars';

    private static $serverProcess = null;
    private static int $serverPort = 0;

    private array $dbConfig = [];
    private ?PDO $pdo = null;
    private string $prefix = '';
    private array $fixture = [];
    private string|false $previousTokenSecret = false;

    protected function setUp(): void
    {
        $this->previousTokenSecret = getenv('APP_TOKEN_SECRET');
        $this->dbConfig = $this->testDatabaseConfig();
        if ($this->dbConfig === []) {
            self::markTestSkipped(
                'Set RBAC_TEST_DB_* and RBAC_TEST_DB_CONFIRM=YES to run isolated API authorization tests.'
            );
        }

        putenv('APP_TOKEN_SECRET=' . self::TOKEN_SECRET);
        $this->startApiServer();

        $probe = $this->request('POST', '/api/auth/login', [
            'username' => 'rbac-schema-probe',
            'password' => 'invalid-test-password',
        ]);
        self::assertSame(401, $probe['status'], 'The disposable test database should initialize before fixtures are seeded.');

        $this->pdo = $this->connectToTestDatabase();
        $this->prefix = 'rbac' . bin2hex(random_bytes(6));
        $this->fixture = [
            'gyms' => [],
            'users' => [],
            'services' => [],
            'contacts' => [],
            'appointments' => [],
        ];
        $this->seedFixture();
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null && $this->prefix !== '') {
            $this->removeFixture();
        }

        if ($this->previousTokenSecret === false) {
            putenv('APP_TOKEN_SECRET');
        } else {
            putenv('APP_TOKEN_SECRET=' . $this->previousTokenSecret);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
            self::$serverProcess = null;
        }
    }

    public function testNonAdminReadsStayWithinTheirGymEvenWhenAnotherGymIsRequested(): void
    {
        $operator = $this->fixture['users']['operator_a'];

        $services = $this->request(
            'GET',
            '/api/services?gym_id=' . $this->fixture['gyms']['b'],
            null,
            $operator
        );
        self::assertSame(200, $services['status']);
        self::assertSame(
            [$this->prefix . '-service-a'],
            array_column($services['json'], 'name')
        );

        $contacts = $this->request(
            'GET',
            '/api/contacts?gym_id=' . $this->fixture['gyms']['b'],
            null,
            $operator
        );
        self::assertSame(200, $contacts['status']);
        self::assertSame(
            [$this->prefix . '-contact-a'],
            array_column($contacts['json'], 'nome')
        );

        $appointments = $this->request(
            'GET',
            '/api/appointments?gym_id=' . $this->fixture['gyms']['b'],
            null,
            $operator
        );
        self::assertSame(200, $appointments['status']);
        self::assertSame(
            [$this->prefix . '-service-a'],
            array_column($appointments['json'], 'service_name')
        );

        $users = $this->request('GET', '/api/users', null, $operator);
        self::assertSame(200, $users['status']);
        $usernames = array_column($users['json'], 'username');
        self::assertContains($this->fixture['users']['owner_a']['username'], $usernames);
        self::assertContains($operator['username'], $usernames);
        self::assertContains($this->fixture['users']['legacy_operator']['username'], $usernames);
        self::assertNotContains($this->fixture['users']['owner_b']['username'], $usernames);
        self::assertNotContains($this->fixture['users']['operator_b']['username'], $usernames);

        $legacyOperator = $this->fixture['users']['legacy_operator'];
        self::assertSame(200, $this->request('GET', '/api/services', null, $legacyOperator)['status']);
        self::assertSame(200, $this->request('GET', '/api/contacts', null, $legacyOperator)['status']);
        self::assertSame(200, $this->request('GET', '/api/appointments', null, $legacyOperator)['status']);
        self::assertSame(200, $this->request('GET', '/api/users', null, $legacyOperator)['status']);

        $gym = $this->request('GET', '/api/gyms?all_categories=1', null, $operator);
        self::assertSame(200, $gym['status']);
        self::assertSame([$this->fixture['gyms']['a']], array_column($gym['json'], 'id'));
    }

    public function testAdminCanReadAcrossGymsAndAccessAdminOnlyEndpoints(): void
    {
        $admin = $this->fixture['users']['admin'];

        foreach ([$admin, $this->fixture['users']['super_admin']] as $administrator) {
            $users = $this->request('GET', '/api/users', null, $administrator);
            self::assertSame(200, $users['status']);
            $usernames = array_column($users['json'], 'username');
            self::assertContains($this->fixture['users']['owner_a']['username'], $usernames);
            self::assertContains($this->fixture['users']['owner_b']['username'], $usernames);

            $contacts = $this->request('GET', '/api/contacts', null, $administrator);
            self::assertSame(200, $contacts['status']);
            $contactNames = array_column($contacts['json'], 'nome');
            self::assertContains($this->prefix . '-contact-a', $contactNames);
            self::assertContains($this->prefix . '-contact-b', $contactNames);

            $appointments = $this->request('GET', '/api/appointments', null, $administrator);
            self::assertSame(200, $appointments['status']);
            $serviceNames = array_column($appointments['json'], 'service_name');
            self::assertContains($this->prefix . '-service-a', $serviceNames);
            self::assertContains($this->prefix . '-service-b', $serviceNames);

            self::assertSame(200, $this->request('GET', '/api/settings', null, $administrator)['status']);
            self::assertSame(200, $this->request('GET', '/api/admin/audit', null, $administrator)['status']);
        }
    }

    public function testOperatorCannotMutateAnotherGymsServicesAppointmentsOrContacts(): void
    {
        $operator = $this->fixture['users']['operator_a'];
        $gymB = $this->fixture['gyms']['b'];
        $serviceA = $this->fixture['services']['a'];
        $serviceB = $this->fixture['services']['b'];
        $appointmentB = $this->fixture['appointments']['b'];
        $contactB = $this->fixture['contacts']['b'];

        $createdService = $this->request(
            'POST',
            '/api/services',
            ['name' => $this->prefix . '-unauthorized-service', 'gym_id' => $gymB],
            $operator
        );
        self::assertSame(200, $createdService['status']);
        $createdServiceGym = $this->pdo->prepare('SELECT gym_id FROM services WHERE id = ?');
        $createdServiceGym->execute([(int)$createdService['json']['id']]);
        self::assertSame(
            $this->fixture['gyms']['a'],
            (int)$createdServiceGym->fetchColumn(),
            'An operator cannot create a service in another gym; the API binds it to their own gym.'
        );

        $createdContact = $this->request(
            'POST',
            '/api/contacts',
            $this->validContactPayload($this->prefix . '-created-contact', 'D') + ['gym_id' => $gymB],
            $operator
        );
        self::assertSame(200, $createdContact['status']);
        $createdContactGym = $this->pdo->prepare('SELECT gym_id FROM visitatori WHERE id = ?');
        $createdContactGym->execute([(int)$createdContact['json']['id']]);
        self::assertSame(
            $this->fixture['gyms']['a'],
            (int)$createdContactGym->fetchColumn(),
            'An operator cannot create a contact in another gym; the API binds it to their own gym.'
        );

        self::assertSame(403, $this->request(
            'PUT',
            '/api/services/' . $serviceA,
            ['name' => $this->prefix . '-service-a', 'gym_id' => $gymB],
            $operator
        )['status']);
        self::assertSame(403, $this->request('DELETE', '/api/services/' . $serviceB, null, $operator)['status']);

        self::assertSame(403, $this->request(
            'POST',
            '/api/appointments',
            [
                'service_id' => $serviceB,
                'contact_id' => $contactB,
                'scheduled_at' => '2030-04-03T09:00:00Z',
                'scheduled_timezone' => 'Europe/Rome',
            ],
            $operator
        )['status']);
        self::assertSame(403, $this->request('PUT', '/api/appointments/' . $appointmentB, [], $operator)['status']);
        self::assertSame(403, $this->request('DELETE', '/api/appointments/' . $appointmentB, null, $operator)['status']);

        self::assertSame(403, $this->request(
            'PUT',
            '/api/gyms/' . $gymB,
            ['name' => $this->prefix . '-gym-b', 'category' => 'gym'],
            $this->fixture['users']['owner_a']
        )['status']);

        $foreignContactBefore = $this->contactName($contactB);
        $updateContact = $this->request(
            'PUT',
            '/api/contacts/' . $contactB,
            $this->validContactPayload($this->prefix . '-unauthorized-change', 'C'),
            $operator
        );
        self::assertSame(404, $updateContact['status']);
        self::assertSame($foreignContactBefore, $this->contactName($contactB));

        self::assertSame(404, $this->request('DELETE', '/api/contacts/' . $contactB, null, $operator)['status']);
        self::assertSame(1, $this->contactCount($contactB));
    }

    public function testOnlyBillingOwnerCanManageUsersAndCannotCrossGymBoundary(): void
    {
        $operator = $this->fixture['users']['operator_a'];
        $owner = $this->fixture['users']['owner_a'];
        $userB = $this->fixture['users']['operator_b'];

        self::assertSame(403, $this->request(
            'POST',
            '/api/users',
            [
                'name' => 'Blocked test user',
                'email' => $this->prefix . '-blocked@example.invalid',
                'username' => $this->prefix . '-blocked',
                'password' => 'integration-test-password',
                'role' => 'OPERATORE',
            ],
            $operator
        )['status']);
        self::assertSame(403, $this->request('PUT', '/api/users/' . $operator['id'], [], $operator)['status']);
        self::assertSame(403, $this->request('DELETE', '/api/users/' . $operator['id'], null, $operator)['status']);

        $created = $this->request(
            'POST',
            '/api/users',
            [
                'name' => 'Created test user',
                'email' => $this->prefix . '-created@example.invalid',
                'username' => $this->prefix . '-created',
                'password' => 'integration-test-password',
                'role' => 'OPERATORE',
                'gym_id' => $this->fixture['gyms']['b'],
            ],
            $owner
        );
        self::assertSame(200, $created['status']);
        $createdUser = $this->pdo->prepare('SELECT gym_id FROM users WHERE id = ?');
        $createdUser->execute([(int)$created['json']['id']]);
        self::assertSame(
            $this->fixture['gyms']['a'],
            (int)$createdUser->fetchColumn(),
            'A billing owner cannot assign a new account to another gym.'
        );

        self::assertSame(403, $this->request('PUT', '/api/users/' . $userB['id'], [], $owner)['status']);
        self::assertSame(403, $this->request('DELETE', '/api/users/' . $userB['id'], null, $owner)['status']);
    }

    public function testAdminOnlyEndpointsRejectOperatorsAndExpiredGymSubscriptionIsBlocked(): void
    {
        $operatorA = $this->fixture['users']['operator_a'];
        self::assertSame(403, $this->request('GET', '/api/settings', null, $operatorA)['status']);
        self::assertSame(403, $this->request('GET', '/api/admin/audit', null, $operatorA)['status']);
        self::assertSame(401, $this->request('GET', '/api/contacts')['status']);

        $this->pdo->prepare("UPDATE gym_subscriptions SET status = 'expired', current_period_end = NULL WHERE gym_id = ?")
            ->execute([$this->fixture['gyms']['b']]);
        self::assertSame(
            402,
            $this->request('GET', '/api/services', null, $this->fixture['users']['operator_b'])['status']
        );
    }

    private function testDatabaseConfig(): array
    {
        if (getenv('RBAC_TEST_DB_CONFIRM') !== 'YES') {
            return [];
        }

        $database = (string)getenv('RBAC_TEST_DB_NAME');
        if (!preg_match('/(^|[_-])test([_-]|$)/i', $database)) {
            self::fail('Refusing to run API authorization tests: RBAC_TEST_DB_NAME must clearly identify a test database.');
        }

        $host = (string)getenv('RBAC_TEST_DB_HOST');
        $user = (string)getenv('RBAC_TEST_DB_USER');
        if ($database === '' || $host === '' || $user === '') {
            return [];
        }

        return [
            'host' => $host,
            'port' => (int)(getenv('RBAC_TEST_DB_PORT') ?: 3306),
            'database' => $database,
            'user' => $user,
            'password' => (string)(getenv('RBAC_TEST_DB_PASSWORD') ?: ''),
        ];
    }

    private function startApiServer(): void
    {
        if (is_resource(self::$serverProcess)) {
            return;
        }

        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            self::fail('Could not allocate a local port for the API authorization tests.');
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        self::$serverPort = (int)substr(strrchr((string)$address, ':'), 1);

        $environment = [
            'DB_HOST' => $this->dbConfig['host'],
            'DB_PORT' => (string)$this->dbConfig['port'],
            'DB_NAME' => $this->dbConfig['database'],
            'DB_USER' => $this->dbConfig['user'],
            'DB_PASSWORD' => $this->dbConfig['password'],
            'APP_TOKEN_SECRET' => self::TOKEN_SECRET,
            'APP_DEBUG' => 'false',
            'ADMIN_INITIAL_PASSWORD' => '',
        ];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', '/dev/null', 'a'],
            2 => ['file', '/dev/null', 'a'],
        ];
        self::$serverProcess = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::$serverPort, 'router.php'],
            $descriptors,
            $pipes,
            dirname(__DIR__),
            $environment
        );
        if (!is_resource(self::$serverProcess)) {
            self::fail('Could not start the local PHP API server.');
        }
        fclose($pipes[0]);

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $connection = @fsockopen('127.0.0.1', self::$serverPort, $errno, $error, 0.1);
            if ($connection !== false) {
                fclose($connection);
                return;
            }
            usleep(100000);
        }

        self::fail('The local PHP API server did not become ready.');
    }

    private function connectToTestDatabase(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->dbConfig['host'],
            $this->dbConfig['port'],
            $this->dbConfig['database']
        );
        return new PDO($dsn, $this->dbConfig['user'], $this->dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function seedFixture(): void
    {
        $this->fixture['gyms']['a'] = $this->insertGym('a');
        $this->fixture['gyms']['b'] = $this->insertGym('b');

        $this->fixture['users']['admin'] = $this->insertUser('admin', 'ADMIN', null);
        $this->fixture['users']['super_admin'] = $this->insertUser('super-admin', 'SUPER', null);
        $this->fixture['users']['owner_a'] = $this->insertUser('owner-a', 'GESTORE', $this->fixture['gyms']['a']);
        $this->fixture['users']['operator_a'] = $this->insertUser('operator-a', 'OPERATORE', $this->fixture['gyms']['a']);
        $this->fixture['users']['legacy_operator'] = $this->insertUser('legacy-operator', 'OPERATOR', $this->fixture['gyms']['a']);
        $this->fixture['users']['owner_b'] = $this->insertUser('owner-b', 'GESTORE', $this->fixture['gyms']['b']);
        $this->fixture['users']['operator_b'] = $this->insertUser('operator-b', 'OPERATORE', $this->fixture['gyms']['b']);

        foreach (['a', 'b'] as $side) {
            $this->pdo->prepare('UPDATE gyms SET billing_owner_user_id = ? WHERE id = ?')
                ->execute([
                    $this->fixture['users']['owner_' . $side]['id'],
                    $this->fixture['gyms'][$side],
                ]);
            $this->pdo->prepare(
                "INSERT INTO gym_subscriptions (gym_id, trial_start_date, status, plan)
                 VALUES (?, NOW(), 'active', 'monthly')
                 ON DUPLICATE KEY UPDATE status = 'active', plan = 'monthly', current_period_end = NULL"
            )->execute([$this->fixture['gyms'][$side]]);
            $this->fixture['services'][$side] = $this->insertService($side);
            $this->fixture['contacts'][$side] = $this->insertContact($side);
            $this->fixture['appointments'][$side] = $this->insertAppointment($side);
        }
    }

    private function insertGym(string $side): int
    {
        $name = $this->prefix . '-gym-' . $side;
        $stmt = $this->pdo->prepare("INSERT INTO gyms (name, slug, category) VALUES (?, ?, 'gym')");
        $stmt->execute([$name, $name]);
        return (int)$this->pdo->lastInsertId();
    }

    private function insertUser(string $label, string $role, ?int $gymId): array
    {
        $username = $this->prefix . '-' . $label;
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (name, email, username, password_hash, role, gym_id, subscription_status)
             VALUES (?, ?, ?, ?, ?, ?, 'active')"
        );
        $stmt->execute([
            $username,
            $username . '@example.invalid',
            $username,
            password_hash('integration-test-password', PASSWORD_DEFAULT),
            $role,
            $gymId,
        ]);

        return [
            'id' => (int)$this->pdo->lastInsertId(),
            'username' => $username,
            'role' => $role,
            'gym_id' => $gymId,
        ];
    }

    private function insertService(string $side): int
    {
        $name = $this->prefix . '-service-' . $side;
        $stmt = $this->pdo->prepare(
            "INSERT INTO services (gym_id, name, slug, category, duration_minutes, capacity)
             VALUES (?, ?, ?, 'test', 30, 5)"
        );
        $stmt->execute([$this->fixture['gyms'][$side], $name, $name]);
        return (int)$this->pdo->lastInsertId();
    }

    private function insertContact(string $side): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO visitatori
             (nome, cognome, codice_fiscale, data_nascita, luogo_nascita, indirizzo, recapito, sesso, gym_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $this->prefix . '-contact-' . $side,
            'TEST',
            $this->testTaxCode($side === 'a' ? 'A' : 'B'),
            '2000-01-01',
            'TEST',
            'TEST ADDRESS',
            '0000000000',
            'M',
            $this->fixture['gyms'][$side],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    private function insertAppointment(string $side): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO appointments
             (service_id, contact_id, customer_name, scheduled_at, scheduled_timezone, status)
             VALUES (?, ?, ?, '2030-04-03 09:00:00', 'Europe/Rome', 'pending')"
        );
        $stmt->execute([
            $this->fixture['services'][$side],
            $this->fixture['contacts'][$side],
            $this->prefix . '-contact-' . $side . ' TEST',
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    private function removeFixture(): void
    {
        $this->pdo->prepare(
            'DELETE FROM admin_audit_log WHERE actor_user_id IN
             (SELECT id FROM users WHERE username LIKE ?)'
        )->execute([$this->prefix . '%']);

        $gymIds = array_values($this->fixture['gyms']);
        $placeholders = implode(',', array_fill(0, count($gymIds), '?'));
        $this->pdo->prepare("DELETE FROM appointments WHERE service_id IN (SELECT id FROM services WHERE gym_id IN ($placeholders))")
            ->execute($gymIds);
        $this->pdo->prepare("DELETE FROM visitatori WHERE gym_id IN ($placeholders)")->execute($gymIds);
        $this->pdo->prepare("DELETE FROM services WHERE gym_id IN ($placeholders)")->execute($gymIds);
        $this->pdo->prepare("UPDATE gyms SET billing_owner_user_id = NULL WHERE id IN ($placeholders)")
            ->execute($gymIds);
        $this->pdo->prepare("DELETE FROM users WHERE username LIKE ? OR gym_id IN ($placeholders)")
            ->execute(array_merge([$this->prefix . '%'], $gymIds));
        $this->pdo->prepare("DELETE FROM gyms WHERE id IN ($placeholders)")->execute($gymIds);
    }

    private function validContactPayload(string $name, string $taxCodeLetter): array
    {
        return [
            'nome' => $name,
            'cognome' => 'TEST',
            'codice_fiscale' => $this->testTaxCode($taxCodeLetter),
            'data_nascita' => '2000-01-01',
            'luogo_nascita' => 'TEST',
            'indirizzo' => 'TEST ADDRESS',
            'recapito' => '0000000000',
            'sesso' => 'M',
        ];
    }

    private function testTaxCode(string $letter): string
    {
        $base = str_repeat($letter, 6) . '00A00A000';
        $odd = [
            '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
            'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
            'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
            'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
        ];
        $even = [
            '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
            'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9,
            'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19,
            'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25,
        ];
        $sum = 0;
        for ($index = 0; $index < 15; $index++) {
            $sum += (($index + 1) % 2 !== 0) ? $odd[$base[$index]] : $even[$base[$index]];
        }
        return $base . chr(65 + ($sum % 26));
    }

    private function contactName(int $contactId): string
    {
        $stmt = $this->pdo->prepare('SELECT nome FROM visitatori WHERE id = ?');
        $stmt->execute([$contactId]);
        return (string)$stmt->fetchColumn();
    }

    private function contactCount(int $contactId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM visitatori WHERE id = ?');
        $stmt->execute([$contactId]);
        return (int)$stmt->fetchColumn();
    }

    private function request(string $method, string $path, ?array $payload = null, ?array $actor = null): array
    {
        $headers = ['Content-Type: application/json'];
        if ($actor !== null) {
            $headers[] = 'Authorization: Bearer ' . create_api_token([
                'id' => $actor['id'],
                'username' => $actor['username'],
                'role' => $actor['role'],
                'gym_id' => $actor['gym_id'],
            ]);
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR),
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);
        $body = file_get_contents('http://127.0.0.1:' . self::$serverPort . $path, false, $context);
        if ($body === false) {
            self::fail('API integration request failed: ' . $method . ' ' . $path);
        }

        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        return [
            'status' => isset($matches[1]) ? (int)$matches[1] : 0,
            'body' => $body,
            'json' => json_decode($body, true),
        ];
    }
}
