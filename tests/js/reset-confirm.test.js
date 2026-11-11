'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const sourcePath = path.join(__dirname, '..', '..', 'web', 'js', 'reset-confirm.js');
const source = fs.readFileSync(sourcePath, 'utf8');

function submitWith(confirmResult) {
    let submitHandler = null;
    const form = {
        getAttribute(name) {
            return name === 'data-reset-confirm' ? 'Удалить данные?' : null;
        },
        addEventListener(type, handler) {
            if (type === 'submit') {
                submitHandler = handler;
            }
        },
    };
    const document = {
        readyState: 'complete',
        querySelector(selector) {
            return selector === 'form[data-reset-confirm]' ? form : null;
        },
        addEventListener() {
            throw new Error('DOMContentLoaded must not be used when the document is already complete');
        },
    };
    const window = {
        confirm() {
            return confirmResult;
        },
    };

    vm.runInNewContext(source, { document, window, console });

    if (typeof submitHandler !== 'function') {
        throw new Error('submit listener was not registered');
    }

    let prevented = false;
    submitHandler({
        preventDefault() {
            prevented = true;
        },
    });

    return prevented;
}

if (submitWith(false) !== true) {
    throw new Error('cancel must call preventDefault and block the request');
}
if (submitWith(true) !== false) {
    throw new Error('confirm must allow the form submission');
}

console.log('reset-confirm: PASS');
