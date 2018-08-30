var Encore = require('@symfony/webpack-encore');

Encore

    // Directory where compiled assets will be stored.
    .setOutputPath('./web/assets/')

    // Public URL path used by the web server to access the output path.
    .setPublicPath('assets/')

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
        './app/Resources/assets/vendor/bootstrap-typeahead.min',
        './app/Resources/assets/vendor/jquery.i18n.min.js',
        './app/Resources/assets/js/application.js',
        './app/Resources/assets/js/dateLocales.js',
        './app/Resources/assets/js/default.js',
        './app/Resources/assets/js/eventdata.js',
        './app/Resources/assets/js/events.js',
        './app/Resources/assets/js/programs.js',
        './app/Resources/assets/css/_mixins.scss',
        './app/Resources/assets/css/application.scss',
        './app/Resources/assets/css/default.scss',
        './app/Resources/assets/css/events.scss',
        './app/Resources/assets/css/programs.scss',
    ])

    // Other options.
    .enableSassLoader()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
;

module.exports = Encore.getWebpackConfig();
