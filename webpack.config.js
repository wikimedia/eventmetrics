var Encore = require('@symfony/webpack-encore');

Encore

    // Directory where compiled assets will be stored.
    .setOutputPath('./public/assets/')

    // Public URL path used by the web server to access the output path.
    .setPublicPath('/assets/')

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
        './app/Resources/assets/js/core_extensions.js',
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
    ])

    // Other options.
    .enableSassLoader()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .enableSingleRuntimeChunk()
;

module.exports = Encore.getWebpackConfig();
