<?php

declare(strict_types=1);

use ILIAS\ResourceStorage\Stakeholder\AbstractResourceStakeholder;

class ilTestArchiveCreatorStakeholder extends AbstractResourceStakeholder
{
    public function getId(): string
    {
        return 'test_archive_creator';
    }

    public function getOwnerOfNewResources(): int
    {
        return $this->default_owner;
    }
}
