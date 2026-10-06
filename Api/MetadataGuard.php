<?php

namespace Kanboard\Plugin\SortaFolders\Api;

use JsonRPC\Exception\AccessDeniedException;
use Kanboard\Api\Authorization\ProjectAuthorization;
use Kanboard\Api\Procedure\ProjectMetadataProcedure;
use Kanboard\Core\Base;
use Kanboard\Core\Security\Role;
use Kanboard\Plugin\SortaFolders\Model\AccessModel;
use Kanboard\Plugin\SortaFolders\Model\FolderModel;

/**
 * A project's folder is stored as project metadata, and core's API lets any project member write
 * project metadata. Filing a project into a folder applies the folder's access grants, so the API
 * calls saveProjectMetadata and removeProjectMetadata come here first (see Plugin.php). The folder
 * key gets the same rule as the Folder page: a project manager, into a folder they can see, or an
 * admin. The key that lists applied grants (AccessModel::METADATA_KEY) is refused for everyone. Every
 * other key goes to core unchanged.
 */
class MetadataGuard extends Base
{
    public function save($project_id, array $values)
    {
        $procedure = new ProjectMetadataProcedure($this->container);
        $procedure->beforeProcedure('saveProjectMetadata');

        if (array_key_exists(AccessModel::METADATA_KEY, $values)) {
            throw new AccessDeniedException('This key belongs to SortaFolders');
        }

        if (! array_key_exists(FolderModel::METADATA_KEY, $values)) {
            return $procedure->saveProjectMetadata($project_id, $values);
        }

        $folderId = (int) $values[FolderModel::METADATA_KEY];
        unset($values[FolderModel::METADATA_KEY]);

        $this->checkFiling($project_id, $folderId);

        if (! empty($values) && ! $procedure->saveProjectMetadata($project_id, $values)) {
            return false;
        }

        return (bool) $this->sortaFolderModel->assign($project_id, $folderId);
    }

    public function remove($project_id, $name)
    {
        $procedure = new ProjectMetadataProcedure($this->container);
        $procedure->beforeProcedure('removeProjectMetadata');

        if ($name === AccessModel::METADATA_KEY) {
            throw new AccessDeniedException('This key belongs to SortaFolders');
        }

        if ($name !== FolderModel::METADATA_KEY) {
            return $procedure->removeProjectMetadata($project_id, $name);
        }

        $this->checkFiling($project_id, 0);

        return (bool) $this->sortaFolderModel->assign($project_id, 0);
    }

    /**
     * The same rule as ProjectFolderController. A call with the application token has no user and is
     * allowed, as core allows it everything.
     */
    private function checkFiling($projectId, $folderId)
    {
        $projectId = (int) $projectId;

        if (empty($this->projectModel->getById($projectId))) {
            throw new AccessDeniedException('Project Not Found');
        }

        if (! $this->userSession->isLogged() || $this->userSession->isAdmin()) {
            return;
        }

        ProjectAuthorization::getInstance($this->container)->check('ProjectMetadataProcedure', 'saveProjectMetadata', $projectId);

        $userId = $this->userSession->getId();

        if ($this->projectUserRoleModel->getUserRole($projectId, $userId) !== Role::PROJECT_MANAGER) {
            throw new AccessDeniedException('Only a project manager can change the folder');
        }

        $options = $this->sortaFolderModel->getFolderOptionsForUser($userId, $this->sortaFolderModel->getFolderId($projectId));

        if (! isset($options[$folderId])) {
            throw new AccessDeniedException('Folder Access Denied');
        }
    }
}
