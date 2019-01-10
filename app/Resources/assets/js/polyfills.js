/**
 * From https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/includes#Polyfill
 * Any copyright is dedicated to the Public Domain. http://creativecommons.org/publicdomain/zero/1.0/
 */
if (!String.prototype.includes) {
    Object.defineProperty(String.prototype, 'includes', {
        value: function (search, start) {
            if (typeof start !== 'number') {
                start = 0
            }

            if (start + search.length > this.length) {
                return false
            } else {
                return this.indexOf(search, start) !== -1
            }
        }
    })
}
