<?php

namespace App\Services;

use Google\Client;
use Google\Service\Directory;
use Google\Service\Directory\User as WorkspaceUser;
use Google\Service\Directory\UserName as WorkspaceUserName;
use Google\Service\Exception as GoogleServiceException;

/**
 * Google Workspace Admin SDK (Directory API) — reads/manages the
 * @crc.pshs.edu.ph directory (students + employees).
 *
 * Uses a service account with domain-wide delegation, impersonating an
 * admin mailbox (config('services.google_workspace.admin_email')) since
 * the Directory API has no concept of acting as "itself" the way a Drive
 * service account can.
 *
 * Setup: see docs discussed with the Workspace super admin — service
 * account authorized for domain-wide delegation with scopes
 * admin.directory.user and admin.directory.orgunit.readonly.
 */
class GoogleWorkspaceDirectoryService
{
    private ?Directory $directory = null;

    public function __construct()
    {
        $credentials = config('services.google_workspace.credentials');
        $adminEmail = config('services.google_workspace.admin_email');

        if (! $credentials || ! $adminEmail) {
            return; // not configured — calls will throw when invoked
        }

        try {
            $client = new Client();
            $client->setAuthConfig($credentials);
            $client->setSubject($adminEmail); // domain-wide delegation impersonation
            $client->addScope(Directory::ADMIN_DIRECTORY_USER);
            $client->addScope(Directory::ADMIN_DIRECTORY_ORGUNIT_READONLY);

            $this->directory = new Directory($client);
        } catch (\Throwable $e) {
            logger()->warning('GoogleWorkspaceDirectoryService: failed to initialize client', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getDirectory(): Directory
    {
        if ($this->directory === null) {
            throw new \RuntimeException('Google Workspace Admin credentials are not configured (GOOGLE_WORKSPACE_CREDENTIALS).');
        }

        return $this->directory;
    }

    /**
     * List all Org Units under the domain (read-only sanity check / OU
     * discovery — used once during setup to confirm exact OU paths).
     *
     * @return array<int, array{orgUnitPath: string, name: string}>
     */
    public function listOrgUnits(): array
    {
        $result = $this->getDirectory()->orgunits->listOrgunits(
            'my_customer',
            ['type' => 'all']
        );

        $units = [];

        foreach ($result->getOrganizationUnits() ?? [] as $ou) {
            $units[] = [
                'orgUnitPath' => $ou->getOrgUnitPath(),
                'name'        => $ou->getName(),
            ];
        }

        return $units;
    }

    /**
     * List directory users for the configured domain, paginated internally.
     * Read-only — used for the Phase 1 review-queue sync.
     *
     * @return array<int, array{primaryEmail: string, fullName: string, orgUnitPath: string}>
     */
    public function listUsers(int $limit = 500): array
    {
        $domain = config('services.google_workspace.domain');
        $users = [];
        $pageToken = null;

        do {
            $result = $this->getDirectory()->users->listUsers([
                'domain'     => $domain,
                'maxResults' => min(500, $limit - count($users)),
                'pageToken'  => $pageToken,
            ]);

            foreach ($result->getUsers() ?? [] as $u) {
                $name = $u->getName();
                $users[] = [
                    'primaryEmail' => $u->getPrimaryEmail(),
                    'fullName'     => $name ? trim(($name->getGivenName() ?? '').' '.($name->getFamilyName() ?? '')) : '',
                    'orgUnitPath'  => $u->getOrgUnitPath(),
                ];
            }

            $pageToken = $result->getNextPageToken();
        } while ($pageToken && count($users) < $limit);

        return $users;
    }

    /**
     * Whether a Workspace account already exists for this address.
     */
    public function emailExists(string $email): bool
    {
        try {
            $this->getDirectory()->users->get($email);

            return true;
        } catch (GoogleServiceException $e) {
            if ($e->getCode() === 404) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Create a new Workspace account. Returns the primary email on success.
     * Caller is responsible for checking emailExists() first to pick a free
     * address — this does not itself resolve collisions.
     */
    public function createUser(
        string $email,
        string $givenName,
        string $familyName,
        string $password,
        string $orgUnitPath,
    ): string {
        $name = new WorkspaceUserName();
        $name->setGivenName($givenName);
        $name->setFamilyName($familyName);

        $user = new WorkspaceUser();
        $user->setPrimaryEmail($email);
        $user->setName($name);
        $user->setPassword($password);
        $user->setChangePasswordAtNextLogin(true);
        $user->setOrgUnitPath($orgUnitPath);

        $created = $this->getDirectory()->users->insert($user);

        return $created->getPrimaryEmail();
    }
}
