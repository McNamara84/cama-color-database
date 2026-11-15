<?php

declare(strict_types=1);

namespace Cama\Tests\Integration;

use Cama\Auth\AdminBootstrap;
use Cama\Auth\DuplicateAccount;
use Cama\Auth\LoginLimiter;
use Cama\Auth\Passwords;
use Cama\Auth\Users;
use Cama\Infrastructure\Database;
use Cama\Tests\AccountDatabase;
use PHPUnit\Framework\TestCase;

final class AccountsTest extends TestCase
{
    private Database $database;
    private Users $users;
    private Passwords $passwords;

    protected function setUp(): void
    {
        $this->database = AccountDatabase::reset();
        $this->users = new Users($this->database);
        $this->passwords = new Passwords();
    }

    public function testBootstrapIsIdempotentAndNeverResetsCredentials(): void
    {
        $bootstrap = new AdminBootstrap($this->users, $this->passwords);
        $admin = $bootstrap->run(' Admin@Example.org ', AccountDatabase::PASSWORD);
        self::assertTrue($admin->isAdmin);
        self::assertTrue($admin->mustChangePassword);
        self::assertSame('admin@example.org', $admin->email);
        self::assertTrue($this->passwords->verify(AccountDatabase::PASSWORD, $admin->passwordHash));
        self::assertNotSame(AccountDatabase::PASSWORD, $admin->passwordHash);
        self::assertFalse($this->passwords->needsRehash($admin->passwordHash));
        $hash = $this->passwords->hash('Changed long password!');
        self::assertTrue($this->users->changePassword($admin, $hash));
        $again = $bootstrap->run('', '');
        self::assertSame($admin->id, $again->id);
        self::assertSame($hash, $again->passwordHash);
        self::assertFalse($again->mustChangePassword);
        self::assertCount(1, $this->users->recent());
    }

    public function testBootstrapRejectsMissingOrWeakConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new AdminBootstrap($this->users, $this->passwords))->run('invalid', 'short');
    }

    public function testExistingMemberCannotBePromotedThroughBootstrap(): void
    {
        $this->users->create('member@example.org', 'Member', $this->passwords->hash(AccountDatabase::PASSWORD));
        try {
            (new AdminBootstrap($this->users, $this->passwords))->run('member@example.org', AccountDatabase::PASSWORD);
            self::fail('Must reject a conflicting email.');
        } catch (DuplicateAccount) {
            self::assertNull($this->users->admin());
            self::assertFalse($this->users->byEmail('member@example.org')->isAdmin);
        }
    }

    public function testUniqueAdminAndEmailConstraintsAndMissingAccounts(): void
    {
        $hash = $this->passwords->hash(AccountDatabase::PASSWORD);
        $admin = $this->users->create('admin@example.org', 'Admin', $hash, true);
        $member = $this->users->create('member@example.org', '<script>🎨</script>', $hash);
        self::assertFalse($member->isAdmin);
        self::assertSame('<script>🎨</script>', $this->users->byId($member->id)->name);
        self::assertSame($admin->id, $this->users->admin()->id);
        self::assertNull($this->users->byId(-1));
        self::assertNull($this->users->byEmail("' OR 1=1 --"));
        self::assertFalse($this->users->recent()[0]['is_admin']);
        foreach ([['admin@example.org', false], ['second@example.org', true]] as [$email, $isAdmin]) {
            try {
                $this->users->create($email, 'Duplicate', $hash, $isAdmin);
                self::fail('Unique constraint must reject this account.');
            } catch (DuplicateAccount) {
                self::assertCount(2, $this->users->recent());
            }
        }
    }

    public function testStalePasswordUpdateCannotOverwriteNewCredentials(): void
    {
        $user = $this->users->create('user@example.org', 'Name', $this->passwords->hash(AccountDatabase::PASSWORD));
        $hash = $this->passwords->hash('First password change!');
        self::assertTrue($this->users->changePassword($user, $hash));
        self::assertFalse($this->users->changePassword($user, $this->passwords->hash('Second password change!')));
        self::assertSame($hash, $this->users->byId($user->id)->passwordHash);
    }

    public function testEmailThrottlingPersistsAndExpires(): void
    {
        $limiter = new LoginLimiter($this->database);
        for ($i = 0; $i < 5; $i++) {
            self::assertTrue($limiter->consume('a@example.org', '192.0.2.' . $i, 1000));
        }
        self::assertFalse((new LoginLimiter($this->database))->consume('a@example.org', '192.0.2.99', 1001));
        self::assertTrue($limiter->consume('a@example.org', '192.0.2.99', 1900));
        $limiter->clearEmail('a@example.org');
        self::assertTrue($limiter->consume('a@example.org', '192.0.2.99', 1901));
        self::assertStringNotContainsString('example.org', json_encode($this->database->connection()->query('SELECT * FROM login_attempts')->fetchAll(), JSON_THROW_ON_ERROR));
    }

    public function testIpThrottlingPreventsSprayingDifferentEmails(): void
    {
        $limiter = new LoginLimiter($this->database);
        for ($i = 0; $i < 30; $i++) {
            self::assertTrue($limiter->consume('account' . $i . '@example.org', '192.0.2.1', 1000));
        }
        self::assertFalse($limiter->consume('another@example.org', '192.0.2.1', 1000));
        self::assertTrue($limiter->consume('another@example.org', '192.0.2.2', 1000));
    }

    public function testConnectionEnforcesStrictModeIndependentlyOfServerDefault(): void
    {
        self::assertStringContainsString('STRICT_TRANS_TABLES', (string) $this->database->connection()->query('SELECT @@SESSION.sql_mode')->fetchColumn());
    }
}
