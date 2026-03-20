/**
 * Store du Page Builder — gère l'arbre de blocs, la sélection et l'undo/redo.
 */

let blocks = $state([]);
let selectedId = $state(null);
let history = $state([]);
let historyIndex = $state(-1);
let dragType = $state(null);

export function getBuilder() {
    return {
        get blocks() { return blocks; },
        get selectedId() { return selectedId; },
        get selectedBlock() { return selectedId ? findBlock(blocks, selectedId) : null; },
        get dragType() { return dragType; },
        get canUndo() { return historyIndex > 0; },
        get canRedo() { return historyIndex < history.length - 1; },

        // Initialise depuis des données sauvegardées
        load(data) {
            blocks = data || [];
            selectedId = null;
            history = [JSON.stringify(blocks)];
            historyIndex = 0;
        },

        // Ajoute un bloc
        addBlock(type, parentId = null, index = -1) {
            const block = createBlock(type);

            if (parentId) {
                const parent = findBlock(blocks, parentId);
                if (parent && parent.children) {
                    if (index >= 0) parent.children.splice(index, 0, block);
                    else parent.children.push(block);
                }
            } else {
                if (index >= 0) blocks.splice(index, 0, block);
                else blocks.push(block);
            }

            blocks = [...blocks];
            selectedId = block.id;
            saveHistory();
            return block;
        },

        // Supprime un bloc
        removeBlock(id) {
            blocks = removeBlockFromTree(blocks, id);
            if (selectedId === id) selectedId = null;
            saveHistory();
        },

        // Met à jour les props d'un bloc
        updateBlock(id, updates) {
            const block = findBlock(blocks, id);
            if (block) {
                Object.assign(block, updates);
                blocks = [...blocks];
                saveHistory();
            }
        },

        // Met à jour une prop spécifique
        updateProp(id, key, value) {
            const block = findBlock(blocks, id);
            if (block) {
                if (!block.props) block.props = {};
                block.props[key] = value;
                blocks = [...blocks];
                saveHistory();
            }
        },

        // Met à jour un style
        updateStyle(id, key, value) {
            const block = findBlock(blocks, id);
            if (block) {
                if (!block.styles) block.styles = {};
                block.styles[key] = value;
                blocks = [...blocks];
                saveHistory();
            }
        },

        // Déplace un bloc (drag-and-drop)
        moveBlock(blockId, newParentId, index) {
            const block = findBlock(blocks, blockId);
            if (!block) return;

            blocks = removeBlockFromTree(blocks, blockId);

            if (newParentId) {
                const parent = findBlock(blocks, newParentId);
                if (parent && parent.children) {
                    parent.children.splice(index, 0, block);
                }
            } else {
                blocks.splice(index, 0, block);
            }

            blocks = [...blocks];
            saveHistory();
        },

        // Duplique un bloc
        duplicateBlock(id) {
            const original = findBlock(blocks, id);
            if (!original) return;

            const clone = JSON.parse(JSON.stringify(original));
            reassignIds(clone);

            // Ajouter après l'original au même niveau
            const parent = findParent(blocks, id);
            if (parent) {
                const idx = parent.children.findIndex(b => b.id === id);
                parent.children.splice(idx + 1, 0, clone);
            } else {
                const idx = blocks.findIndex(b => b.id === id);
                blocks.splice(idx + 1, 0, clone);
            }

            blocks = [...blocks];
            selectedId = clone.id;
            saveHistory();
        },

        select(id) { selectedId = id; },
        deselect() { selectedId = null; },

        setDragType(type) { dragType = type; },
        clearDragType() { dragType = null; },

        undo() {
            if (historyIndex > 0) {
                historyIndex--;
                blocks = JSON.parse(history[historyIndex]);
                selectedId = null;
            }
        },

        redo() {
            if (historyIndex < history.length - 1) {
                historyIndex++;
                blocks = JSON.parse(history[historyIndex]);
                selectedId = null;
            }
        },

        // Exporte l'arbre en JSON
        toJSON() { return JSON.parse(JSON.stringify(blocks)); },

        // Rendu HTML
        toHTML() { return blocksToHTML(blocks); },
    };
}

function saveHistory() {
    const snap = JSON.stringify(blocks);
    history = history.slice(0, historyIndex + 1);
    history.push(snap);
    historyIndex = history.length - 1;
    if (history.length > 50) { history.shift(); historyIndex--; }
}

function generateId() { return 'b_' + Math.random().toString(36).substr(2, 9); }

function createBlock(type) {
    const base = { id: generateId(), type, props: {}, styles: {}, children: [] };

    switch (type) {
        case 'section': return { ...base, props: { tag: 'section' }, styles: { padding: '40px 20px' }, children: [] };
        case 'container': return { ...base, styles: { maxWidth: '1200px', margin: '0 auto', padding: '0 20px' }, children: [] };
        case 'columns': return { ...base, props: { columns: 2, gap: '20px' }, children: [
            { id: generateId(), type: 'column', props: {}, styles: {}, children: [] },
            { id: generateId(), type: 'column', props: {}, styles: {}, children: [] },
        ]};
        case 'column': return { ...base };
        case 'heading': return { ...base, props: { text: 'Titre', level: 2 } };
        case 'text': return { ...base, props: { text: 'Votre texte ici. Cliquez pour modifier.' } };
        case 'image': return { ...base, props: { src: '', alt: 'Image', width: '100%' } };
        case 'button': return { ...base, props: { text: 'Cliquez ici', url: '#', variant: 'primary' } };
        case 'divider': return { ...base, styles: { borderTop: '1px solid #e0e0e0', margin: '20px 0' } };
        case 'spacer': return { ...base, props: { height: 40 } };
        case 'video': return { ...base, props: { url: '', type: 'youtube' } };
        case 'form': return { ...base, props: { formSlug: '' } };
        case 'html': return { ...base, props: { code: '<div>Code HTML personnalisé</div>' } };
        case 'hero': return { ...base, props: { title: 'Bienvenue', subtitle: 'Votre site commence ici', bgColor: '#1a1a2e', textColor: '#ffffff', ctaText: 'Commencer', ctaUrl: '#' }, children: [] };
        case 'card': return { ...base, props: { title: 'Titre', description: 'Description', imageUrl: '' } };
        case 'gallery': return { ...base, props: { images: [], columns: 3 } };
        case 'testimonial': return { ...base, props: { quote: 'Super produit !', author: 'Jean Dupont', role: 'Client' } };
        case 'pricing': return { ...base, props: { title: 'Pro', price: '29', period: '/mois', features: ['Feature 1', 'Feature 2', 'Feature 3'], ctaText: 'Choisir' } };
        case 'faq': return { ...base, props: { items: [{ q: 'Question ?', a: 'Réponse.' }] } };
        case 'navbar': return { ...base, props: { brand: 'MonSite', links: [{ text: 'Accueil', url: '/' }, { text: 'À propos', url: '/about' }] } };
        case 'footer': return { ...base, props: { text: '© 2026 MonSite. Tous droits réservés.', links: [] } };
        case 'map': return { ...base, props: { lat: 45.5017, lng: -73.5673, zoom: 12 } };
        case 'countdown': return { ...base, props: { targetDate: '2026-12-31T00:00:00' } };
        case 'social': return { ...base, props: { networks: ['facebook', 'twitter', 'instagram', 'linkedin'] } };
        default: return base;
    }
}

function findBlock(tree, id) {
    for (const block of tree) {
        if (block.id === id) return block;
        if (block.children) {
            const found = findBlock(block.children, id);
            if (found) return found;
        }
    }
    return null;
}

function findParent(tree, id, parent = null) {
    for (const block of tree) {
        if (block.id === id) return parent ? { children: tree } : null;
        if (block.children) {
            const found = findParent(block.children, id, block);
            if (found) return found;
        }
    }
    return null;
}

function removeBlockFromTree(tree, id) {
    return tree.filter(b => {
        if (b.id === id) return false;
        if (b.children) b.children = removeBlockFromTree(b.children, id);
        return true;
    });
}

function reassignIds(block) {
    block.id = generateId();
    if (block.children) block.children.forEach(reassignIds);
}

function blocksToHTML(tree) {
    return tree.map(blockToHTML).join('\n');
}

function blockToHTML(b) {
    const s = b.styles || {};
    const p = b.props || {};
    const style = Object.entries(s).map(([k, v]) => `${camelToKebab(k)}:${v}`).join(';');
    const styleAttr = style ? ` style="${style}"` : '';
    const children = (b.children || []).map(blockToHTML).join('\n');

    switch (b.type) {
        case 'section': return `<section${styleAttr}>${children}</section>`;
        case 'container': return `<div${styleAttr}>${children}</div>`;
        case 'columns': return `<div style="display:grid;grid-template-columns:repeat(${p.columns||2},1fr);gap:${p.gap||'20px'}">${children}</div>`;
        case 'column': return `<div${styleAttr}>${children}</div>`;
        case 'heading': return `<h${p.level||2}${styleAttr}>${esc(p.text||'')}</h${p.level||2}>`;
        case 'text': return `<p${styleAttr}>${esc(p.text||'')}</p>`;
        case 'image': return `<img src="${esc(p.src||'')}" alt="${esc(p.alt||'')}" style="width:${p.width||'100%'};${style}" />`;
        case 'button': return `<a href="${esc(p.url||'#')}" style="display:inline-block;padding:12px 24px;background:#ff3e00;color:#fff;border-radius:6px;text-decoration:none;${style}">${esc(p.text||'')}</a>`;
        case 'divider': return `<hr${styleAttr} />`;
        case 'spacer': return `<div style="height:${p.height||40}px"></div>`;
        case 'html': return p.code || '';
        case 'hero': return `<div style="background:${p.bgColor||'#1a1a2e'};color:${p.textColor||'#fff'};padding:80px 20px;text-align:center"><h1>${esc(p.title||'')}</h1><p>${esc(p.subtitle||'')}</p>${p.ctaText?`<a href="${esc(p.ctaUrl||'#')}" style="display:inline-block;margin-top:20px;padding:12px 24px;background:#ff3e00;color:#fff;border-radius:6px;text-decoration:none">${esc(p.ctaText)}</a>`:''}</div>`;
        case 'navbar': return `<nav style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;${style}"><b>${esc(p.brand||'')}</b><div>${(p.links||[]).map(l=>`<a href="${esc(l.url)}" style="margin-left:16px">${esc(l.text)}</a>`).join('')}</div></nav>`;
        case 'footer': return `<footer style="padding:24px;text-align:center;${style}">${esc(p.text||'')}</footer>`;
        default: return `<div${styleAttr}>${children || esc(p.text || '')}</div>`;
    }
}

function esc(s) { return (s||'').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function camelToKebab(s) { return s.replace(/([A-Z])/g, '-$1').toLowerCase(); }
