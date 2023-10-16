const { join, resolve } = require('path');

const admin_path = process.env.ADMIN_PATH || resolve('../../../../../../../src/Administration/Resources/app/administration');
const artifactsPath = process.env.ARTIFACTS_PATH ? join(process.env.ARTIFACTS_PATH, '/build/artifacts/jest') : 'coverage';

process.env.ADMIN_PATH = admin_path;

module.exports = {
    preset: '@shopware-ag/jest-preset-sw6-admin',
    globals: {
        adminPath: process.env.ADMIN_PATH,
    },
    displayName: {
        name: 'LanguagePack Administration',
        color: 'lime'
    },

    reporters: [
        'default', [
            'jest-junit',
            {
                'suiteName': 'SocialShopping Administration',
                'outputDirectory': artifactsPath,
                'outputName': 'social-shopping-administration-jest.xml',
                'uniqueOutputName': 'false'
            },
        ],
    ],

    setupFilesAfterEnv: [
        resolve(join(process.env.ADMIN_PATH, '/test/_setup/prepare_environment.js')),
    ],

    moduleDirectories:[
        '<rootDir>/node_modules',
        resolve(join(process.env.ADMIN_PATH, '/node_modules')),
    ],

    moduleNameMapper: {
        '^uuid$': require.resolve('uuid'),
        '^\@shopware-ag\/admin-extension-sdk\/es\/(.*)': resolve(join(process.env.ADMIN_PATH, '/node_modules')) + '/@shopware-ag/admin-extension-sdk/umd/$1',
        '^test(.*)$': '<rootDir>/test$1',
        vue$: 'vue/dist/vue.common.dev.js',
    },

    testMatch: [
        '<rootDir>/test/**/*.spec.js'
    ],

    collectCoverage: true,
    collectCoverageFrom: ['src/**/*.(t|j)s'],
    coverageDirectory: artifactsPath,

    transformIgnorePatterns: [
        '/node_modules/(?!(@shopware-ag/meteor-icon-kit|uuidv7|other)/)',
    ],
};
