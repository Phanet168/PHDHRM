<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'facilities.view', 'facilities.create', 'facilities.update', 'facilities.delete', 'facilities.publish',
            'news.view', 'news.create', 'news.update', 'news.delete', 'news.restore', 'news.submit', 'news.approve', 'news.publish',
            'legal-documents.view', 'legal-documents.create', 'legal-documents.update', 'legal-documents.delete', 'legal-documents.publish',
            'health-content.view', 'health-content.create', 'health-content.update', 'health-content.delete', 'health-content.submit', 'health-content.approve', 'health-content.publish', 'health-content.import',
            'partners.manage',
            'hero-slides.manage',
            'leader-recommendations.manage',
            'homepage-quick-links.manage',
            'health-messages.manage',
            'page-banners.manage',
            'about.manage',
            'menus.manage',
            'media.manage',
            'media.view', 'media.upload', 'media.edit', 'media.move', 'media.delete', 'media.copy', 'media.restore',
            'media.folder.create', 'media.folder.rename', 'media.folder.move', 'media.folder.delete',
            'users.manage',
            'roles.manage',
            'settings.manage',
            'audit-logs.view',
            'content.view', 'content.create', 'content.edit', 'content.delete',
            'content.submit_review', 'content.approve', 'content.publish',
            'content.custom_html', 'content.manage_gallery', 'content.manage_categories',
            'page.view', 'page.create', 'page.edit', 'page.delete', 'page.restore',
            'page.submit_review', 'page.review', 'page.approve', 'page.publish', 'page.unpublish',
            'page.manage_menu', 'page.manage_media', 'page.custom_html',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($permissions);

        $websiteAdmin = Role::firstOrCreate(['name' => 'Website Admin', 'guard_name' => 'web']);
        $websiteAdmin->syncPermissions([
            'facilities.view', 'facilities.create', 'facilities.update', 'facilities.delete', 'facilities.publish',
            'news.view', 'news.create', 'news.update', 'news.delete', 'news.restore', 'news.submit', 'news.approve', 'news.publish',
            'legal-documents.view', 'legal-documents.create', 'legal-documents.update', 'legal-documents.delete', 'legal-documents.publish',
            'health-content.view', 'health-content.create', 'health-content.update', 'health-content.delete', 'health-content.submit', 'health-content.approve', 'health-content.publish', 'health-content.import',
            'partners.manage', 'hero-slides.manage', 'leader-recommendations.manage', 'homepage-quick-links.manage', 'health-messages.manage', 'page-banners.manage', 'about.manage', 'menus.manage', 'media.manage', 'settings.manage',
            'media.view', 'media.upload', 'media.edit', 'media.move', 'media.delete', 'media.copy', 'media.restore',
            'media.folder.create', 'media.folder.rename', 'media.folder.move', 'media.folder.delete',
            'content.view', 'content.create', 'content.edit', 'content.delete',
            'content.submit_review', 'content.approve', 'content.publish',
            'content.custom_html', 'content.manage_gallery', 'content.manage_categories',
            'page.view', 'page.create', 'page.edit', 'page.delete', 'page.restore',
            'page.submit_review', 'page.review', 'page.approve', 'page.publish', 'page.unpublish',
            'page.manage_menu', 'page.manage_media', 'page.custom_html',
        ]);

        $hospitalAdmin = Role::firstOrCreate(['name' => 'Hospital Admin', 'guard_name' => 'web']);
        $hospitalAdmin->syncPermissions([
            'facilities.view', 'facilities.update',
            'news.view', 'news.create', 'news.update', 'news.submit',
            'media.manage', 'media.view', 'media.upload',
            'content.view', 'content.create', 'content.edit', 'content.submit_review', 'content.manage_gallery',
        ]);

        $healthCenterAdmin = Role::firstOrCreate(['name' => 'Health Center Admin', 'guard_name' => 'web']);
        $healthCenterAdmin->syncPermissions([
            'facilities.view', 'facilities.update',
            'news.view', 'news.create', 'news.update', 'news.submit',
            'media.manage', 'media.view', 'media.upload',
            'content.view', 'content.create', 'content.edit', 'content.submit_review', 'content.manage_gallery',
        ]);

        $editor = Role::firstOrCreate(['name' => 'Editor', 'guard_name' => 'web']);
        $editor->syncPermissions([
            'facilities.view', 'facilities.create', 'facilities.update',
            'news.view', 'news.create', 'news.update', 'news.submit',
            'legal-documents.view', 'legal-documents.create', 'legal-documents.update',
            'health-content.view', 'health-content.create', 'health-content.update', 'health-content.submit', 'health-content.import',
            'media.manage', 'media.view', 'media.upload', 'media.edit', 'media.move', 'media.copy',
            'media.folder.create', 'media.folder.rename',
            'content.view', 'content.create', 'content.edit', 'content.submit_review', 'content.manage_gallery', 'content.manage_categories',
            'page.view', 'page.create', 'page.edit', 'page.submit_review', 'page.manage_media',
        ]);

        $reviewer = Role::firstOrCreate(['name' => 'Reviewer', 'guard_name' => 'web']);
        $reviewer->syncPermissions([
            'news.view', 'news.approve', 'news.publish',
            'health-content.view', 'health-content.approve', 'health-content.publish',
            'legal-documents.view', 'legal-documents.publish',
            'content.view', 'content.approve', 'content.publish',
            'page.view', 'page.review', 'page.approve', 'page.publish', 'page.unpublish',
        ]);
    }
}
