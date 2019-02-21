<?php

return [
    /**
     * A list of directories that should be parsed for class and
     * method information. After excluding the directories
     * defined in exclude_analysis_directory_list, the remaining
     * files will be statically analyzed for errors.
     *
     * Thus, both first-party and third-party code being used by
     * your application should be included in this list.
     */
    'directory_list' => [
        'src/',
        'vendor/',
    ],

    /**
     * A list of directories holding code that we want
     * to parse, but not analyze. Also works for individual
     * files.
     */
    'exclude_analysis_directory_list' => [
        'vendor/',
    ],

    /**
     * Add any issue types (such as 'PhanUndeclaredMethod')
     * to this black-list to inhibit them from being reported.
     */
    'suppress_issue_types' => [
        // Doesn't understand usage in annottions, PHPCS is better
        'PhanUnreferencedUseNormal',
    ],
];
