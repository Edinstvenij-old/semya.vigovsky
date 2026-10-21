/**
 * Shrinks phone photos in the browser before upload: a 5–10 MB camera shot
 * becomes ~300–600 KB, plus a small thumbnail for grids. Re-encoding also
 * drops EXIF metadata, including the GPS location of where the photo was taken.
 * Anything the browser cannot decode (PDF, HEIC outside Safari, GIF) is sent as is.
 */

function canvasBlob(bitmap, maxSide, quality) {
    const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff'; // transparent PNGs get a white background instead of black
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
}

/**
 * @returns {Promise<{file: File, thumb: File|null}>}
 */
export async function prepareImage(file, { maxSide = 1920, quality = 0.82, thumbSide = 480 } = {}) {
    if (!file.type.startsWith('image/') || file.type === 'image/gif') return { file, thumb: null };

    let bitmap;
    try {
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        return { file, thumb: null };
    }

    try {
        const [full, small] = await Promise.all([
            canvasBlob(bitmap, maxSide, quality),
            canvasBlob(bitmap, thumbSide, 0.75),
        ]);
        const base = file.name.replace(/\.[^.]+$/, '') || 'photo';
        // Camera formats are always re-encoded (strips GPS). Screenshots (PNG/WebP) carry no location,
        // and flat-colour images can grow as JPEG, so for them the smaller file wins.
        const isCameraFormat = /jpe?g|hei[cf]/i.test(file.type);
        const useCompressed = full && (isCameraFormat || full.size < file.size);
        return {
            file: useCompressed ? new File([full], `${base}.jpg`, { type: 'image/jpeg' }) : file,
            thumb: small ? new File([small], `${base}-thumb.jpg`, { type: 'image/jpeg' }) : null,
        };
    } finally {
        bitmap.close?.();
    }
}

/** Appends files[i] and their thumbnails thumbs[i] to a FormData. */
export async function appendImages(body, files, options) {
    const prepared = await Promise.all([...files].map((f) => prepareImage(f, options)));
    prepared.forEach(({ file, thumb }, i) => {
        body.append(`files[${i}]`, file);
        if (thumb) body.append(`thumbs[${i}]`, thumb);
    });
    return prepared;
}
