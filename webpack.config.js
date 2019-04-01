var Encore = require('@symfony/webpack-encore'),
    CopyWebpackPlugin = require( 'copy-webpack-plugin' );

Encore

    // Directory where compiled assets will be stored.
    .setOutputPath('./public/assets/')

    // Public URL path used by the web server to access the output path.
    .setPublicPath('/assets/')

    // Copy i18n files for use by jquery.i18n.
    .addPlugin( new CopyWebpackPlugin( [
        { from: './node_modules/jquery.uls/i18n/', to: 'jquery.uls.18n/' },
    ] ) )

    /*
     * ENTRY CONFIG
     *
     * Add 1 entry for each "page" of your app
     * (including one that's included on every page - e.g. "app")
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if you JavaScript imports CSS.
     */
    .addEntry('app', [
        './app/Resources/assets/vendor/jquery.i18n.dist.js',
        './app/Resources/assets/vendor/bootstrap-typeahead.min',
        './app/Resources/assets/js/application.js',
        './app/Resources/assets/js/dateLocales.js',
        './app/Resources/assets/js/default.js',
        './app/Resources/assets/js/eventedit.js',
        './app/Resources/assets/js/eventshow.js',
        './app/Resources/assets/js/eventdata.js',
        './app/Resources/assets/js/programs.js',
        './app/Resources/assets/css/_mixins.scss',
        './app/Resources/assets/css/application.scss',
        './app/Resources/assets/css/default.scss',
        './app/Resources/assets/css/events.scss',
        './app/Resources/assets/css/programs.scss',
        './vendor/wikimedia/toolforge-bundle/Resources/assets/toolforge.js',
        './public/images/logo.svg',
        './public/favicon.ico',

        // Universal Language Selector.
        './node_modules/jquery.uls/src/jquery.uls.data.js',
        './node_modules/jquery.uls/src/jquery.uls.data.utils.js',
        './node_modules/jquery.uls/src/jquery.uls.lcd.js',
        './node_modules/jquery.uls/src/jquery.uls.languagefilter.js',
        './node_modules/jquery.uls/src/jquery.uls.core.js',
        './node_modules/jquery.uls/css/jquery.uls.css',
        './node_modules/jquery.uls/css/jquery.uls.grid.css',
        './node_modules/jquery.uls/css/jquery.uls.lcd.css',

    ])

    // Other options.
    .enableSassLoader()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .enableSingleRuntimeChunk()
;

module.exports = Encore.getWebpackConfig();
