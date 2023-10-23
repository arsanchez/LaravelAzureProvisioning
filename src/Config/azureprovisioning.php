<?php

use RobTrehy\LaravelAzureProvisioning\Utils\SCIMConstantsV2;

return [

    /**
     * Set the prefix for the SCIM service routes
     */
    'routePrefix' => 'scim/v2.0',
    'routeMiddleware' => [
    ],

    /**
     * User Resource Type settings
     */
    'Users' => [
        'singular' => 'User',
        'description' => 'User Account',
        'schema' => [SCIMConstantsV2::SCHEMA_ENTERPRISE_USER, SCIMConstantsV2::SCHEMA_USER],

        /**
         * Specifiy the model that should be used.
         *
         * Default: config('auth.providers.users.model')
         * Default: App\Models\User::class
         */
        'model' => config('auth.providers.users.model'),

        /**
         * Request validation rules.
         * All fields that you wish to map to an attribute must
         * be included in the validation or it will be ignored.
         */
        'validations' => [
            'username' => 'required',
            'active' => 'boolean',
            'displayname' => 'required',
            'password' => 'nullable',
            'emails' => 'required|array',
        ],

        /**
         * Specify relations to eager load
         */
        'relations' => [],

        /**
         * Specifiy which attributes should not be included in a response.
         *
         * Default: ['password']
         */
        'exclude' => [
            'password'
        ],

        /**
         * Specify default values for attributes that are not nullable.
         */
        'defaults' => [
            'password' => time().random_bytes(5),
            'active' => false,
        ],

        /**
         * Declare the SCIM attributes to map to your Model attributes.
         *
         * Required Fields: id
         */
        'mapping' => [
            'id' => 'id',
            'username' => 'username',
            'active' => 'active',
            'displayname' => 'name',
            'password' => 'password',
            'emails.work.value' => 'email',
        ],
    ],

    /**
     * Group Resource Type settings
     */
    'Groups' => [
        'singular' => 'Group',
        'description' => 'User Groups',
        'schema' => [SCIMConstantsV2::SCHEMA_GROUP],

        /**
         * Specify the model that should be used.
         *
         * Default: Spatie\Permission\Models\Role::class
         */
        'model' => Spatie\Permission\Models\Role::class,

        /**
         * Request validation rules.
         * All fields that you wish to use map to an attribute must
         * be included in the validation or it will be ignored.
         */
        'validations' => [
            'displayname' => 'required',
            'members' => 'nullable|array',
        ],

        /**
         * Specify relations to eager load
         */
        'relations' => [],

        /**
         * Specifiy which attributes should not be included in a response.
         *
         * Default: []
         */
        'exclude' => [],

        /**
         * Specify default values for attributes that are not nullable.
         */
        'defaults' => [],

        /**
         * Declare the SCIM attributes to map to your Model attributes.
         *
         * Required Fields: id, displayname, members
         */
        'mapping' => [
            'id' => 'id',
            'displayname' => 'name',
            /**
             * Provide the methods to call on the User object that
             * will assign and remove the User to/from a Group using
             * the Group name
             *
             * Array[0] must be the method to add to a Group
             * Array[1] must be the method to remove from a Group
             *
             * Default: ['assignRole', 'removeRole']
             * E.G. $user->assignRole('GroupName');
             */
            'members' => ['assignRole', 'removeRole']
        ],
    ],
];
