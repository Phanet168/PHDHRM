<?php

namespace App\Policies;

use App\Models\LegalDocument;
use App\Models\User;

class LegalDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('legal-documents.view');
    }

    public function view(User $user, LegalDocument $legalDocument): bool
    {
        return $user->can('legal-documents.view');
    }

    public function create(User $user): bool
    {
        return $user->can('legal-documents.create');
    }

    public function update(User $user, LegalDocument $legalDocument): bool
    {
        return $user->can('legal-documents.update');
    }

    public function delete(User $user, LegalDocument $legalDocument): bool
    {
        return $user->can('legal-documents.delete');
    }

    public function publish(User $user, LegalDocument $legalDocument): bool
    {
        return $user->can('legal-documents.publish');
    }
}
