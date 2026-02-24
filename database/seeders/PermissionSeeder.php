<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name_en'        => 'Discount on room rent',
                'name_ar'        => 'الخصم على اجار الغرفة',
                'description_en' => 'Allows applying discounts on room rent invoices',
                'description_ar' => 'يسمح بإضافة خصومات على فواتير إيجار الغرف',
            ],
            [
                'name_en'        => 'Access accounting reports',
                'name_ar'        => 'الوصول لتقارير المحاسبة',
                'description_en' => 'Grants access to view and generate accounting reports',
                'description_ar' => 'يمنح صلاحية عرض وإنشاء تقارير المحاسبة',
                'active' => 1,
            ],
            [
                'name_en'        => 'Access device control',
                'name_ar'        => 'الوصول للتحكم بالأجهزة',
                'description_en' => 'Enables control over connected devices within the system',
                'description_ar' => 'يمكن المستخدم من التحكم بالأجهزة المرتبطة داخل النظام',
                'active' => 1,

            ],
            [
                'name_en'        => 'Access general control settings',
                'name_ar'        => 'الوصول للإعدادات العامة للتحكم',
                'description_en' => 'Allows managing general control settings',
                'description_ar' => 'يسمح بإدارة الإعدادات العامة للتحكم',
                'active' => 1,

            ],
            [
                'name_en'        => 'Access building settings',
                'name_ar'        => 'الوصول لإعدادات المبنى',
                'description_en' => 'Allows configuration and management of building settings',
                'description_ar' => 'يسمح بتهيئة وإدارة إعدادات المبنى',
                'active' => 1,

            ],
            [
                'name_en'        => 'Access general program settings',
                'name_ar'        => 'الوصول للإعدادات العامة للبرنامج',
                'description_en' => 'Allows access to overall program settings',
                'description_ar' => 'يتيح الوصول إلى الإعدادات العامة للبرنامج',
                'active' => 1,

            ],
            [
                'name_en'        => 'Access room service request reports',
                'name_ar'        => 'الوصول لتقارير طلبات خدمة الغرف',
                'description_en' => 'Grants access to reports of room service requests',
                'description_ar' => 'يمنح صلاحية الوصول لتقارير طلبات خدمة الغرف',
                'active' => 1,

            ],
            [
                'name_en'        => 'Access device control reports',
                'name_ar'        => 'الوصول لتقارير التحكم بالأجهزة',
                'description_en' => 'Grants access to reports related to device control actions',
                'description_ar' => 'يمنح صلاحية الوصول لتقارير متعلقة بالتحكم بالأجهزة',
                'active' => 1,

            ],
        ];

        Permission::insert($permissions);
    }
}
