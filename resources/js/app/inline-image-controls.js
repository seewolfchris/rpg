const INLINE_IMAGE_CONTROLS_SELECTOR = '[data-inline-image-controls]';
const DEFAULT_MAX_SLOTS = 4;

function normalizeMaxSlots(rawValue) {
    const value = Number.parseInt(String(rawValue || ''), 10);

    return Number.isFinite(value) && value > 0 ? value : DEFAULT_MAX_SLOTS;
}

function normalizeSlot(rawSlot, maxSlots) {
    const slot = Number.parseInt(String(rawSlot || ''), 10);

    if (!Number.isFinite(slot) || slot < 1 || slot > maxSlots) {
        return null;
    }

    return slot;
}

function markerForSlot(slot) {
    return `[bild:${slot}]`;
}

function firstFreeSlot(occupiedSlots, maxSlots) {
    for (let slot = 1; slot <= maxSlots; slot += 1) {
        if (!occupiedSlots.has(slot)) {
            return slot;
        }
    }

    return null;
}

export function insertInlineImageMarker(value, selectionStart, selectionEnd, marker) {
    const content = typeof value === 'string' ? value : '';
    const start = Math.max(0, Math.min(Number(selectionStart) || 0, content.length));
    const end = Math.max(start, Math.min(Number(selectionEnd) || start, content.length));
    const markerText = String(marker || '');
    const nextValue = `${content.slice(0, start)}${markerText}${content.slice(end)}`;
    const cursorPosition = start + markerText.length;

    return {
        value: nextValue,
        selectionStart: cursorPosition,
        selectionEnd: cursorPosition,
    };
}

export function projectInlineImageSlots(existingItems = [], files = [], maxSlots = DEFAULT_MAX_SLOTS) {
    const slotLimit = normalizeMaxSlots(maxSlots);
    const occupiedSlots = new Set();
    const existing = Array.from(existingItems).map((item, index) => ({
        id: item?.id ?? null,
        index,
        removed: Boolean(item?.removed),
        sourceSlot: normalizeSlot(item?.slot, slotLimit),
        projectedSlot: null,
        marker: null,
    }));
    const unslottedExisting = [];

    existing.forEach((item) => {
        if (item.removed) {
            return;
        }

        if (item.sourceSlot !== null && !occupiedSlots.has(item.sourceSlot)) {
            occupiedSlots.add(item.sourceSlot);
            item.projectedSlot = item.sourceSlot;
            item.marker = markerForSlot(item.sourceSlot);

            return;
        }

        unslottedExisting.push(item);
    });

    unslottedExisting.forEach((item) => {
        const slot = firstFreeSlot(occupiedSlots, slotLimit);

        if (slot === null) {
            return;
        }

        occupiedSlots.add(slot);
        item.projectedSlot = slot;
        item.marker = markerForSlot(slot);
    });

    const projectedFiles = Array.from(files).map((file, index) => {
        const slot = firstFreeSlot(occupiedSlots, slotLimit);

        if (slot !== null) {
            occupiedSlots.add(slot);
        }

        return {
            index,
            name: typeof file?.name === 'string' ? file.name : '',
            projectedSlot: slot,
            marker: slot === null ? null : markerForSlot(slot),
        };
    });

    const freeSlots = [];
    for (let slot = 1; slot <= slotLimit; slot += 1) {
        if (!occupiedSlots.has(slot)) {
            freeSlots.push(slot);
        }
    }

    return {
        existing,
        files: projectedFiles,
        freeSlots,
        activeStoredCount: existing.filter((item) => !item.removed).length + projectedFiles.length,
        maxSlots: slotLimit,
    };
}

function resolveTextarea(scope) {
    const selector = String(scope.dataset.inlineImageTarget || '').trim();

    if (selector !== '') {
        const target = document.querySelector(selector);

        if (target instanceof HTMLTextAreaElement) {
            return target;
        }
    }

    const form = scope.closest('form');
    const fallback = form?.querySelector('textarea[name="description"], textarea[name="content"]');

    return fallback instanceof HTMLTextAreaElement ? fallback : null;
}

function insertMarkerIntoTextarea(textarea, marker) {
    const nextState = insertInlineImageMarker(
        textarea.value,
        textarea.selectionStart,
        textarea.selectionEnd,
        marker,
    );

    textarea.value = nextState.value;
    textarea.focus();
    textarea.setSelectionRange(nextState.selectionStart, nextState.selectionEnd);
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    textarea.dispatchEvent(new Event('change', { bubbles: true }));
}

function setFileInputFiles(fileInput, files) {
    if (typeof DataTransfer === 'undefined') {
        if (files.length === 0) {
            fileInput.value = '';
        }

        return;
    }

    const transfer = new DataTransfer();

    files.forEach((file) => {
        transfer.items.add(file);
    });

    fileInput.files = transfer.files;
}

function fileFromProjection(files, projection) {
    return files[projection.index] || null;
}

function renderExistingProjection(scope, projection) {
    const existingNodes = Array.from(scope.querySelectorAll('[data-inline-image-existing]'));

    projection.existing.forEach((projectedItem) => {
        const node = existingNodes[projectedItem.index];

        if (!(node instanceof HTMLElement)) {
            return;
        }

        const label = node.querySelector('[data-inline-image-slot-label]');
        const markerButton = node.querySelector('[data-inline-image-insert-marker]');

        if (label instanceof HTMLElement) {
            if (projectedItem.removed) {
                label.textContent = 'Entfernt';
            } else if (projectedItem.projectedSlot !== null) {
                label.textContent = `Slot ${projectedItem.projectedSlot}`;
            } else {
                label.textContent = 'Galerie';
            }
        }

        if (markerButton instanceof HTMLButtonElement) {
            markerButton.disabled = projectedItem.removed || projectedItem.projectedSlot === null;

            if (projectedItem.projectedSlot !== null) {
                markerButton.dataset.inlineImageSlot = String(projectedItem.projectedSlot);
                markerButton.textContent = `Marker ${markerForSlot(projectedItem.projectedSlot)}`;
            }
        }
    });
}

function createPreviewCard({ projectedFile, file, objectUrl, onRemove }) {
    const card = document.createElement('div');
    card.className = 'rounded-lg border border-stone-700/80 bg-black/35 p-3';

    if (objectUrl !== '') {
        const image = document.createElement('img');
        image.src = objectUrl;
        image.alt = projectedFile.name || 'Neues Bild';
        image.loading = 'lazy';
        image.className = 'h-40 w-full rounded-md object-cover';
        card.appendChild(image);
    }

    const controls = document.createElement('div');
    controls.className = 'mt-3 flex flex-wrap items-center gap-2 text-xs text-stone-300';

    const slotLabel = document.createElement('span');
    slotLabel.className = 'rounded border border-stone-600/80 px-2 py-1 text-stone-200';
    slotLabel.textContent = projectedFile.projectedSlot === null ? 'Ohne Slot' : `Slot ${projectedFile.projectedSlot}`;
    controls.appendChild(slotLabel);

    if (projectedFile.marker !== null) {
        const markerButton = document.createElement('button');
        markerButton.type = 'button';
        markerButton.dataset.inlineImageInsertMarker = '';
        markerButton.dataset.inlineImageSlot = String(projectedFile.projectedSlot);
        markerButton.className = 'rounded-md border border-amber-500/70 px-2 py-1 font-semibold text-amber-100 transition hover:bg-amber-500/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300';
        markerButton.textContent = `Marker ${projectedFile.marker}`;
        controls.appendChild(markerButton);
    }

    const removeButton = document.createElement('button');
    removeButton.type = 'button';
    removeButton.className = 'rounded-md border border-stone-600/80 px-2 py-1 text-stone-200 transition hover:border-stone-400 hover:text-stone-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300';
    removeButton.textContent = 'Entfernen';
    removeButton.addEventListener('click', onRemove);
    controls.appendChild(removeButton);

    card.appendChild(controls);

    const name = document.createElement('p');
    name.className = 'mt-2 truncate text-xs text-stone-500';
    name.textContent = file?.name || 'Neue Datei';
    card.appendChild(name);

    return card;
}

function setupInlineImageControl(scope) {
    if (!(scope instanceof HTMLElement) || scope.dataset.inlineImageControlsEnhanced === '1') {
        return;
    }

    const fileInput = scope.querySelector('[data-inline-image-file-input]');
    const previewList = scope.querySelector('[data-inline-image-preview-list]');
    const statusNode = scope.querySelector('[data-inline-image-status]');
    const textarea = resolveTextarea(scope);
    const maxSlots = normalizeMaxSlots(scope.dataset.inlineImageMaxSlots);

    if (!(fileInput instanceof HTMLInputElement) || !(previewList instanceof HTMLElement)) {
        return;
    }

    scope.dataset.inlineImageControlsEnhanced = '1';

    let selectedFiles = Array.from(fileInput.files || []);
    let objectUrls = [];

    const revokeObjectUrls = () => {
        objectUrls.forEach((objectUrl) => URL.revokeObjectURL(objectUrl));
        objectUrls = [];
    };

    const readExistingItems = () => Array.from(scope.querySelectorAll('[data-inline-image-existing]')).map((node, index) => {
        const checkbox = node.querySelector('[data-inline-image-remove]');

        return {
            id: node instanceof HTMLElement ? node.dataset.inlineImageMediaId || String(index) : String(index),
            slot: node instanceof HTMLElement ? node.dataset.inlineImageExistingSlot : '',
            removed: checkbox instanceof HTMLInputElement && checkbox.checked,
        };
    });

    const render = () => {
        revokeObjectUrls();

        const projection = projectInlineImageSlots(readExistingItems(), selectedFiles, maxSlots);
        renderExistingProjection(scope, projection);
        previewList.innerHTML = '';

        projection.files.forEach((projectedFile) => {
            const file = fileFromProjection(selectedFiles, projectedFile);
            const objectUrl = file instanceof File && String(file.type || '').startsWith('image/')
                ? URL.createObjectURL(file)
                : '';

            if (objectUrl !== '') {
                objectUrls.push(objectUrl);
            }

            previewList.appendChild(createPreviewCard({
                projectedFile,
                file,
                objectUrl,
                onRemove: () => {
                    selectedFiles.splice(projectedFile.index, 1);
                    setFileInputFiles(fileInput, selectedFiles);
                    render();
                },
            }));
        });

        if (statusNode instanceof HTMLElement) {
            const count = projection.activeStoredCount;
            const warning = count > projection.maxSlots
                ? ' Mehr als 4 gespeicherte Bilder werden nicht akzeptiert.'
                : '';
            statusNode.textContent = count === 0
                ? ''
                : `Geplant: ${count} von ${projection.maxSlots} gespeicherten Bildern.${warning}`;
        }
    };

    fileInput.addEventListener('change', () => {
        const incomingFiles = Array.from(fileInput.files || []);
        selectedFiles = selectedFiles.concat(incomingFiles);
        setFileInputFiles(fileInput, selectedFiles);
        render();
    });

    scope.querySelectorAll('[data-inline-image-remove]').forEach((checkbox) => {
        checkbox.addEventListener('change', render);
    });

    scope.addEventListener('click', (event) => {
        const markerButton = event.target instanceof Element
            ? event.target.closest('[data-inline-image-insert-marker]')
            : null;

        if (!(markerButton instanceof HTMLButtonElement)) {
            return;
        }

        const slot = normalizeSlot(markerButton.dataset.inlineImageSlot, maxSlots);

        if (slot === null || textarea === null) {
            return;
        }

        insertMarkerIntoTextarea(textarea, markerForSlot(slot));
    });

    const form = scope.closest('form');
    form?.addEventListener('submit', revokeObjectUrls);
    window.addEventListener('pagehide', revokeObjectUrls, { once: true });

    render();
}

export function setupInlineImageControls(root = document) {
    const scope = root instanceof HTMLElement || root instanceof Document
        ? root
        : document;

    scope.querySelectorAll(INLINE_IMAGE_CONTROLS_SELECTOR).forEach(setupInlineImageControl);
}
