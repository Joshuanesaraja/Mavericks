import CryptoJS from 'crypto-js';

const AES_KEY = import.meta.env.VITE_AES_KEY;

function getKey() {
    if (!AES_KEY) {
        throw new Error('AES key is not configured');
    }

    return CryptoJS.SHA256(AES_KEY);
}

// Encryption

export function encryptPayload(data) {
    const plaintext = JSON.stringify(data);

    const iv = CryptoJS.lib.WordArray.random(16);

    const encrypted = CryptoJS.AES.encrypt(
        plaintext,
        getKey(),
        {
            iv,
            mode: CryptoJS.mode.CBC,
            padding: CryptoJS.pad.Pkcs7,
        }
    );

    const combined = iv.clone().concat(encrypted.ciphertext);

    return CryptoJS.enc.Base64.stringify(combined);
}

// Decryption

export function decryptPayload(encryptedPayload) {
    const encrypted = CryptoJS.enc.Base64.parse(encryptedPayload);

    const iv = CryptoJS.lib.WordArray.create(
        encrypted.words.slice(0, 4),
        16
    );

    const ciphertext = CryptoJS.lib.WordArray.create(
        encrypted.words.slice(4),
        encrypted.sigBytes - 16
    );

    const decrypted = CryptoJS.AES.decrypt(
        {
            ciphertext,
        },
        getKey(),
        {
            iv,
            mode: CryptoJS.mode.CBC,
            padding: CryptoJS.pad.Pkcs7,
        }
    );

    const plaintext = decrypted.toString(CryptoJS.enc.Utf8);

    if (!plaintext) {
        throw new Error('Failed to decrypt payload');
    }

    return JSON.parse(plaintext);
}