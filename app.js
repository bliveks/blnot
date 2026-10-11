const { useState, useEffect, useRef } = React;

const TXT = {
    notesTitle: 'Qeydl\u0259r',
    loginHint: 'Daxil olmaq \u00fc\u00e7\u00fcn hesab m\u0259lumatlar\u0131n\u0131 yaz\u0131n',
    username: '\u0130stifad\u0259\u00e7i ad\u0131',
    password: '\u015eifr\u0259',
    checking: 'Yoxlan\u0131l\u0131r...',
    login: 'Daxil ol',
    noNotes: 'H\u0259l\u0259 qeyd yoxdur',
    cancel: 'L\u0259\u011fv et',
    editNote: 'Qeydi redakt\u0259 et',
    newNote: 'Yeni qeyd',
    note: 'Qeyd',
    notePlaceholder: 'Qeydinizi yaz\u0131n...',
    catAdded: 'Kateqoriya \u0259lav\u0259 edildi',
    catUpdated: 'Kateqoriya yenil\u0259ndi',
    edit: 'Redakt\u0259',
    done: 'Haz\u0131r',
    catName: 'Kateqoriya ad\u0131',
    icon: '\u0130kon',
    color: 'R\u0259ng',
    noteUpdated: 'Qeyd yenil\u0259ndi',
    noteAdded: 'Qeyd \u0259lav\u0259 edildi',
    error: 'X\u0259ta ba\u015f verdi',
    relinkFail: 'Qeyd g\u00fcnc\u0259ll\u0259nm\u0259si u\u011fursuz oldu',
    all: 'Ham\u0131s\u0131',
    notesNav: 'Qeydl\u0259r',
    logout: '\u00c7\u0131x\u0131\u015f',
    finished: 'Bitmi\u015f',
    pickCategory: 'Yeni qeyd \u00fc\u00e7\u00fcn kateqoriya se\u00e7in',
    loginFailed: 'Giri\u015f u\u011fursuz oldu',
    loginNeed: 'Username v\u0259 \u015fifr\u0259 daxil edin'
};

const ICONS = [
    'fa-folder','fa-briefcase','fa-user','fa-cart-shopping','fa-lightbulb',
    'fa-heart','fa-star','fa-book','fa-code','fa-music','fa-camera',
    'fa-gamepad','fa-gift','fa-plane','fa-graduation-cap','fa-utensils',
    'fa-home','fa-car','fa-dumbbell','fa-paw','fa-baby','fa-bookmark',
    'fa-bell','fa-calendar','fa-clock','fa-envelope','fa-pen',
    'fa-palette','fa-cube','fa-leaf','fa-globe','fa-mountain-sun',
    'fa-umbrella','fa-mug-hot','fa-key','fa-wrench','fa-diamond',
    'fa-microphone','fa-headphones','fa-desktop','fa-laptop'
];

const COLORS = [
    '#007aff','#34c759','#ff9500','#ff3b30','#af52de',
    '#5856d6','#00c7be','#ff6482','#ffcc00','#8e8e93',
    '#64d2ff','#30d158','#ffd60a','#ff453a','#bf5af2'
];

const AZ_MONTHS = ['Yan','Fev','Mar','Apr','May','Iyn','Iyl','Avq','Sen','Okt','Noy','Dek'];
const AZ_DAYS = ['B.','B.e','\u00c7.a','\u00c7\u0259r','C.a','C\u00fcm','\u015e\u0259n'];

function pad(n) { return n < 10 ? '0' + n : '' + n; }

function formatTime(d) {
    const date = new Date(d);
    if (isNaN(date)) return '';
    const now = new Date();
    const diff = now - date;
    const time = pad(date.getHours()) + ':' + pad(date.getMinutes());
    if (diff < 86400000) return time;
    if (diff < 604800000) return AZ_DAYS[date.getDay()] + ' ' + time;
    return date.getDate() + ' ' + AZ_MONTHS[date.getMonth()] + ' ' + date.getFullYear();
}

function Login({ onLogin }) {
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const userRef = useRef(null);

    useEffect(() => { userRef.current?.focus(); }, []);

    const submit = (e) => {
        e.preventDefault();
        if (loading) return;

        const u = username.trim();
        if (!u || !password) {
            setError(TXT.loginNeed);
            return;
        }

        setLoading(true);
        setError('');

        fetch('functions.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: u, password })
        })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    onLogin(d.csrf_token || '');
                    return;
                }
                setError(d.error || TXT.loginFailed);
            })
            .catch(() => setError(TXT.error))
            .finally(() => setLoading(false));
    };

    return (
        <div className="login-page">
            <div className="login-box">
                <h1>{TXT.notesTitle}</h1>
                <p>{TXT.loginHint}</p>
                <form className="login-form" onSubmit={submit}>
                    <input ref={userRef} className="login-input" type="text" autoComplete="username" placeholder={TXT.username} value={username} onChange={e => setUsername(e.target.value)} disabled={loading} />
                    <input className="login-input" type="password" autoComplete="current-password" placeholder={TXT.password} value={password} onChange={e => setPassword(e.target.value)} disabled={loading} />
                    {error && <div className="login-error">{error}</div>}
                    <button className="login-submit" type="submit" disabled={loading}>{loading ? TXT.checking : TXT.login}</button>
                </form>
            </div>
        </div>
    );
}

function NoteList({ notes, categories, onDelete, onEdit, onPreviewAttachments, onToggle, onMove, catFilter, statusFilter }) {
    const filtered = notes.filter(n => {
        const catOk = catFilter === 'all' || n.category === catFilter;
        const statusOk = statusFilter === 'all' || (statusFilter === 'done' && n.completed) || (statusFilter === 'undone' && !n.completed);
        return catOk && statusOk;
    });

    const autoScroll = event => {
        const edgeSize = 100;
        const scrollStep = 24;
        if (event.clientY < edgeSize) {
            window.scrollBy(0, -scrollStep);
        } else if (event.clientY > window.innerHeight - edgeSize) {
            window.scrollBy(0, scrollStep);
        }
    };

    const clearDragStyles = () => {
        window.removeEventListener('dragover', autoScroll);
        document.querySelectorAll('.note-row.dragging, .note-row.drag-over, .note-row.drag-over-after').forEach(element => {
            element.classList.remove('dragging', 'drag-over', 'drag-over-after');
        });
    };

    const getDropPlacement = event => {
        const bounds = event.currentTarget.getBoundingClientRect();
        return event.clientY > bounds.top + bounds.height / 2 ? 'after' : 'before';
    };

    const handleDragStart = (event, note) => {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', note.id);
        window.removeEventListener('dragover', autoScroll);
        window.addEventListener('dragover', autoScroll);
        event.currentTarget.closest('.note-row')?.classList.add('dragging');
    };

    const handleDragOver = event => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        const placement = getDropPlacement(event);
        document.querySelectorAll('.note-row.drag-over, .note-row.drag-over-after').forEach(element => {
            element.classList.remove('drag-over', 'drag-over-after');
        });
        event.currentTarget.classList.add(placement === 'after' ? 'drag-over-after' : 'drag-over');
    };

    const handleDrop = (event, targetNote) => {
        event.preventDefault();
        event.stopPropagation();
        const draggedId = event.dataTransfer.getData('text/plain');
        const draggedNote = notes.find(note => note.id === draggedId);
        const placement = getDropPlacement(event);
        clearDragStyles();
        if (draggedNote && draggedNote.id !== targetNote.id) onMove(draggedNote, targetNote, placement);
    };

    const handleListDragOver = event => {
        if (event.target !== event.currentTarget) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        document.querySelectorAll('.note-row.drag-over, .note-row.drag-over-after').forEach(element => {
            element.classList.remove('drag-over', 'drag-over-after');
        });
        if (filtered.length > 0) {
            document.querySelector(`[data-note-id="${filtered[filtered.length - 1].id}"]`)?.classList.add('drag-over-after');
        }
    };

    const handleListDrop = event => {
        if (event.target !== event.currentTarget || filtered.length === 0) return;
        event.preventDefault();
        const draggedId = event.dataTransfer.getData('text/plain');
        const draggedNote = notes.find(note => note.id === draggedId);
        const lastVisibleNote = filtered[filtered.length - 1];
        clearDragStyles();
        if (draggedNote && draggedNote.id !== lastVisibleNote.id) onMove(draggedNote, lastVisibleNote, 'after');
    };

    if (filtered.length === 0) {
        return <div className="empty-state"><i className="fa-regular fa-note-sticky"></i><p>{TXT.noNotes}</p></div>;
    }

    return (
        <div className="note-list" onDragOver={catFilter !== 'all' ? handleListDragOver : undefined} onDrop={catFilter !== 'all' ? handleListDrop : undefined}>
            {filtered.map(note => {
                const cat = categories.find(c => c.id === note.category);
                const isDraggable = catFilter !== 'all';
                return (
                    <div key={note.id} data-note-id={note.id} className="note-row" onDragOver={isDraggable ? handleDragOver : undefined} onDrop={isDraggable ? event => handleDrop(event, note) : undefined} style={{ borderLeft: cat ? `4px solid ${cat.color}` : '4px solid rgba(255, 255, 255, 0.2)' }}>
                        {isDraggable && <button type="button" className="note-drag-handle" draggable="true" title="Sıralamaq üçün buradan sürüşdürün" aria-label="Qeydi sıralamaq üçün sürüşdür" onDragStart={event => handleDragStart(event, note)} onDragEnd={clearDragStyles}></button>}
                        <div className={`note-check ${note.completed ? 'done' : ''}`} onClick={() => onToggle(note)}></div>
                        <div className="note-info">
                            <div className="note-row-header">{catFilter === 'all' && cat ? <span className="note-badge" style={{ background: cat.color }}>{cat.label}</span> : null}</div>
                            <div className={`note-text ${note.completed ? 'done' : ''}`}>{note.content}</div>
                            <div className="note-row-meta">
                                <span className="note-date">{formatTime(note.createdAt)}</span>
                                {note.attachments?.length > 0 && <span className="note-attachment-count" title={`${note.attachments.length} əlavə faylı gör`} role="button" tabIndex="0" onClick={() => onPreviewAttachments(note)} onKeyDown={event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); onPreviewAttachments(note); } }}><i className="fa-solid fa-paperclip"></i>{note.attachments.length}</span>}
                            </div>
                        </div>
                        <div className="note-actions">
                            <button className="note-action" title="Redaktə et" aria-label="Qeydi redaktə et" onClick={() => onEdit(note)}><i className="fa-regular fa-pen-to-square"></i></button>
                            <button className="note-action del" title="Sil" aria-label="Qeydi sil" onClick={() => onDelete(note)}><i className="fa-regular fa-trash-can"></i></button>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function NoteModal({ note, categories, currentCat, onSave, onDeleteAttachment, onClose, closing }) {
    const [text, setText] = useState(note?.content || '');
    const noteId = note?.id || '';
    const [attachments, setAttachments] = useState(note?.attachments || []);
    const [pendingFiles, setPendingFiles] = useState([]);
    const [isSaving, setIsSaving] = useState(false);
    const [deletingAttachmentId, setDeletingAttachmentId] = useState(null);
    const [status, setStatus] = useState('');
    const fileInputRef = useRef(null);
    const cat = note?.category || (currentCat !== 'all' ? currentCat : '');
    const catObj = categories.find(c => c.id === cat);
    const isBusy = isSaving || deletingAttachmentId !== null;

    const formatSize = size => size >= 1024 * 1024 ? `${(size / (1024 * 1024)).toFixed(1)} MB` : `${Math.max(1, Math.ceil(size / 1024))} KB`;
    const openFilePicker = () => { if (!isBusy) fileInputRef.current?.click(); };
    const addFiles = event => {
        const files = Array.from(event.target.files || []);
        event.target.value = '';
        if (files.length === 0 || isBusy) return;

        const tooLarge = files.find(file => file.size > 10 * 1024 * 1024);
        if (tooLarge) {
            setStatus(`“${tooLarge.name}” 10 MB-dan böyükdür və əlavə edilmədi.`);
            return;
        }
        if (!noteId && !text.trim()) {
            setStatus('Fayl əlavə etmək üçün əvvəl qeyd mətnini yazın.');
            return;
        }

        setPendingFiles(current => [...current, ...files]);
        setStatus(`${files.length} fayl qeyd yadda saxlananda yüklənəcək.`);
    };
    const removePendingFile = index => {
        if (isBusy) return;
        setPendingFiles(current => current.filter((_, currentIndex) => currentIndex !== index));
    };
    const removeAttachment = async attachment => {
        if (isBusy) return;

        setDeletingAttachmentId(attachment.id);
        setStatus(`“${attachment.name}” silinir…`);
        try {
            await onDeleteAttachment(noteId, attachment.id);
            setAttachments(current => current.filter(currentAttachment => currentAttachment.id !== attachment.id));
            setStatus(`“${attachment.name}” silindi.`);
        } catch (error) {
            setStatus(error.message || TXT.error);
        } finally {
            setDeletingAttachmentId(null);
        }
    };

    const handleSave = async () => {
        if (isBusy) return;
        if (!text.trim()) {
            setStatus('Qeyd mətni boş ola bilməz.');
            return;
        }

        setIsSaving(true);
        setStatus('Qeyd yadda saxlanılır…');
        try {
            await onSave({
                ...note,
                id: noteId || undefined,
                content: text.trim(),
                category: cat,
            }, pendingFiles, (completed, total) => {
                setStatus(`Fayllar yüklənir (${completed}/${total})…`);
            });
        } catch (error) {
            setStatus(error.message || TXT.error);
            setIsSaving(false);
        }
    };

    return (
        <div className={`modal-overlay active ${closing ? 'closing' : ''}`}>
            <div className="modal note-modal" onClick={e => e.stopPropagation()}>
                <div className="modal-header"><button onClick={onClose} disabled={isBusy}>{TXT.cancel}</button><span className="modal-title">{noteId ? TXT.editNote : TXT.newNote}</span><button onClick={handleSave} disabled={isBusy}>{isSaving ? 'Saxlanılır…' : 'Yadda saxla'}</button></div>
                {catObj && <div className="note-modal-category"><div className="modal-cat-tag" style={{ color: catObj.color }}><i className={`fa-solid ${catObj.icon}`}></i>{catObj.label}</div></div>}
                <div className="modal-body">
                    <div className="form-group">
                        <label className="form-label">{TXT.note}</label>
                        <textarea className="form-textarea" value={text} onChange={e => setText(e.target.value)} placeholder={TXT.notePlaceholder} autoFocus rows={4} disabled={isBusy}></textarea>
                        <div className={`attachment-uploader ${isBusy ? 'busy' : ''}`} role="button" tabIndex={isBusy ? -1 : 0} aria-disabled={isBusy} onClick={openFilePicker} onKeyDown={event => { if (!isBusy && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); openFilePicker(); } }}>
                            <div className="attachment-picker">
                                <i className={`fa-solid ${isSaving && pendingFiles.length > 0 ? 'fa-spinner fa-spin' : 'fa-paperclip'}`}></i>
                                <span>{isSaving && pendingFiles.length > 0 ? 'Fayllar yüklənir…' : 'Şəkil və ya sənəd əlavə et'}</span>
                            </div>
                            <input ref={fileInputRef} type="file" multiple disabled={isBusy} accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,text/plain,.doc,.docx,.xls,.xlsx,.ppt,.pptx" onChange={addFiles} />
                            <p className="attachment-help">JPG, PNG, GIF, WEBP, PDF və Office sənədləri — hər biri maksimum 10 MB.</p>
                            {status && <p className="attachment-status" role="status" aria-live="polite">{status}</p>}
                            {(attachments.length > 0 || pendingFiles.length > 0) && <div className="attachment-list" onClick={event => event.stopPropagation()}>
                                {attachments.map(attachment => <div className="attachment-item" key={attachment.id}>
                                    {attachment.mimeType.startsWith('image/') ? <img className="attachment-thumb" src={attachment.url} alt="" /> : <i className="fa-regular fa-file attachment-icon"></i>}
                                    <a href={attachment.url} target="_blank" rel="noopener noreferrer" className="attachment-name">{attachment.name}</a>
                                    <span className="attachment-size">{formatSize(attachment.sizeBytes)}</span>
                                    <button type="button" className="attachment-remove" title="Faylı sil" aria-label={`${attachment.name} faylını sil`} disabled={isBusy} onClick={() => removeAttachment(attachment)}><i className={`fa-solid ${deletingAttachmentId === attachment.id ? 'fa-spinner fa-spin' : 'fa-xmark'}`}></i></button>
                                </div>)}
                                {pendingFiles.map((file, index) => <div className="attachment-item pending" key={`${file.name}-${file.size}-${file.lastModified}-${index}`}>
                                    <i className="fa-regular fa-file attachment-icon"></i>
                                    <span className="attachment-name">{file.name}</span>
                                    <span className="attachment-size">{formatSize(file.size)}</span>
                                    <button type="button" className="attachment-remove" title="Faylı siyahıdan çıxar" aria-label={`${file.name} faylını siyahıdan çıxar`} disabled={isBusy} onClick={() => removePendingFile(index)}><i className="fa-solid fa-xmark"></i></button>
                                </div>)}
                            </div>}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function AttachmentModal({ note, onClose, onUploadAttachments, onSaveOrder, onDeleteAttachment }) {
    const [attachments, setAttachments] = useState(note.attachments || []);
    const [uploadingFiles, setUploadingFiles] = useState([]);
    const [status, setStatus] = useState('');
    const [deletingAttachmentId, setDeletingAttachmentId] = useState(null);
    const [draggedAttachmentId, setDraggedAttachmentId] = useState(null);
    const [dropTargetId, setDropTargetId] = useState(null);
    const [isSavingOrder, setIsSavingOrder] = useState(false);
    const fileInputRef = useRef(null);
    const draggedAttachmentIdRef = useRef(null);
    const isBusy = uploadingFiles.length > 0 || isSavingOrder || deletingAttachmentId !== null;

    useEffect(() => {
        setAttachments(note.attachments || []);
    }, [note.attachments]);
    const formatSize = size => size >= 1024 * 1024 ? `${(size / (1024 * 1024)).toFixed(1)} MB` : `${Math.max(1, Math.ceil(size / 1024))} KB`;
    const openFilePicker = () => { if (!isBusy) fileInputRef.current?.click(); };

    const addFiles = async files => {
        if (isBusy || files.length === 0) return;
        const tooLarge = files.find(file => file.size > 10 * 1024 * 1024);
        if (tooLarge) {
            setStatus(`“${tooLarge.name}” 10 MB-dan böyükdür və yüklənmədi.`);
            return;
        }

        setUploadingFiles(files);
        setStatus('Fayllar yüklənir…');
        try {
            const result = await onUploadAttachments(note, files, (completed, total) => setStatus(`Fayllar yüklənir (${completed}/${total})…`));
            if (result.attachments.length > 0) {
                setAttachments(current => [...current, ...result.attachments]);
            }
            setStatus(result.errors.length > 0
                ? `${result.attachments.length} fayl yükləndi, ${result.errors.length} fayl yüklənmədi: ${result.errors[0]}`
                : `${result.attachments.length} fayl uğurla yükləndi.`);
        } catch (error) {
            setStatus(error.message || TXT.error);
        } finally {
            setUploadingFiles([]);
        }
    };

    const handleFileInput = event => {
        const files = Array.from(event.target.files || []);
        event.target.value = '';
        addFiles(files);
    };

    const reorderAttachments = async reordered => {
        setAttachments(reordered);
        setIsSavingOrder(true);
        setStatus('Fayl sırası yadda saxlanılır…');
        try {
            await onSaveOrder(note.id, reordered);
            setStatus('');
        } catch (error) {
            setStatus(error.message || TXT.error);
        } finally {
            setIsSavingOrder(false);
        }
    };

    const removeAttachment = async attachment => {
        if (isBusy) return;
        setDeletingAttachmentId(attachment.id);
        try {
            await onDeleteAttachment(note.id, attachment.id);
            setAttachments(current => current.filter(currentAttachment => currentAttachment.id !== attachment.id));
        } catch (error) {
            setStatus(error.message || TXT.error);
        } finally {
            setDeletingAttachmentId(null);
        }
    };

    const handleAttachmentDrop = event => {
        event.preventDefault();
        const sourceId = event.dataTransfer.getData('application/x-note-attachment') || event.dataTransfer.getData('text/plain') || draggedAttachmentIdRef.current || draggedAttachmentId;
        const targetId = event.currentTarget.dataset.attachmentId;
        draggedAttachmentIdRef.current = null;
        setDraggedAttachmentId(null);
        setDropTargetId(null);
        if (isBusy || !sourceId || sourceId === targetId) return;

        const sourceIndex = attachments.findIndex(attachment => attachment.id === sourceId);
        const targetIndex = attachments.findIndex(attachment => attachment.id === targetId);
        if (sourceIndex === -1 || targetIndex === -1) return;

        const reordered = [...attachments];
        const [moved] = reordered.splice(sourceIndex, 1);
        reordered.splice(reordered.findIndex(attachment => attachment.id === targetId), 0, moved);
        reorderAttachments(reordered);
    };

    const handleAddCardDrop = event => {
        event.preventDefault();
        const files = Array.from(event.dataTransfer.files || []);
        const sourceId = event.dataTransfer.getData('application/x-note-attachment') || event.dataTransfer.getData('text/plain') || draggedAttachmentIdRef.current || draggedAttachmentId;
        draggedAttachmentIdRef.current = null;
        setDraggedAttachmentId(null);
        setDropTargetId(null);
        if (files.length > 0) {
            addFiles(files);
            return;
        }
        if (isBusy || !sourceId || attachments[attachments.length - 1]?.id === sourceId) return;
        const reordered = attachments.filter(attachment => attachment.id !== sourceId);
        const moved = attachments.find(attachment => attachment.id === sourceId);
        if (moved) reorderAttachments([...reordered, moved]);
    };

    const allowDrop = event => {
        if (!isBusy) event.preventDefault();
    };

    return (
        <div className="modal-overlay active">
            <div className="modal attachment-modal" onClick={event => event.stopPropagation()}>
                <div className="modal-header"><span style={{ width: 50 }}></span><span className="modal-title">Əlavə fayllar</span><button onClick={onClose} disabled={isBusy}>{TXT.done}</button></div>
                <div className="modal-body">
                    <p className="attachment-modal-note">{note.content}</p>
                    <p className="attachment-modal-help">Faylların yerini dəyişmək üçün kartı sürüşdürün. Yeni fayl əlavə etmək üçün “+” kartına klikləyin və ya faylı onun üzərinə atın.</p>
                    <div className="attachment-preview-list">
                        {attachments.map(attachment => <div
                            key={attachment.id}
                            data-attachment-id={attachment.id}
                            className={`attachment-preview-card ${draggedAttachmentId === attachment.id ? 'dragging' : ''} ${dropTargetId === attachment.id ? 'drop-target' : ''}`}
                            draggable={!isBusy}
                            onDragStart={event => {
                                event.dataTransfer.effectAllowed = 'move';
                                event.dataTransfer.setData('application/x-note-attachment', attachment.id);
                                event.dataTransfer.setData('text/plain', attachment.id);
                                draggedAttachmentIdRef.current = attachment.id;
                                setDraggedAttachmentId(attachment.id);
                            }}
                            onDragEnd={() => { draggedAttachmentIdRef.current = null; setDraggedAttachmentId(null); setDropTargetId(null); }}
                            onDragOver={allowDrop}
                            onDragEnter={() => { if (!isBusy && draggedAttachmentId !== attachment.id) setDropTargetId(attachment.id); }}
                            onDragLeave={() => setDropTargetId(null)}
                            onDrop={handleAttachmentDrop}
                        >
                            <a href={attachment.url} target="_blank" rel="noopener noreferrer" className="attachment-preview-link" draggable="false">
                                {attachment.mimeType.startsWith('image/') ? <img className="attachment-preview-image" src={attachment.url} alt={attachment.name} /> : <i className="fa-regular fa-file attachment-preview-icon"></i>}
                                <span className="attachment-preview-name">{attachment.name}</span>
                                <span className="attachment-preview-size">{formatSize(attachment.sizeBytes)}</span>
                            </a>
                            <button
                                type="button"
                                className="attachment-preview-remove"
                                title="Faylı sil"
                                aria-label={`${attachment.name} faylını sil`}
                                disabled={isBusy}
                                draggable="false"
                                onMouseDown={event => event.stopPropagation()}
                                onClick={event => { event.preventDefault(); event.stopPropagation(); removeAttachment(attachment); }}
                            ><i className={`fa-solid ${deletingAttachmentId === attachment.id ? 'fa-spinner fa-spin' : 'fa-trash-can'}`}></i></button>
                            <span className="attachment-drag-handle" title="Sıralamaq üçün sürüşdürün" aria-hidden="true"><i className="fa-solid fa-grip-vertical"></i></span>
                        </div>)}
                        <div
                            role="button"
                            tabIndex={isBusy ? -1 : 0}
                            className={`attachment-preview-card attachment-add-card ${isBusy ? 'busy' : ''} ${dropTargetId === 'add' ? 'drop-target' : ''}`}
                            aria-disabled={isBusy}
                            onClick={openFilePicker}
                            onKeyDown={event => { if (!isBusy && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); openFilePicker(); } }}
                            onDragOver={allowDrop}
                            onDragEnter={() => { if (!isBusy) setDropTargetId('add'); }}
                            onDragLeave={() => setDropTargetId(null)}
                            onDrop={handleAddCardDrop}
                            aria-label="Yeni fayl əlavə et"
                        >
                            <i className={`fa-solid ${uploadingFiles.length > 0 ? 'fa-spinner fa-spin' : 'fa-plus'} attachment-add-icon`}></i>
                            <span>{uploadingFiles.length > 0 ? 'Yüklənir…' : 'Fayl əlavə et'}</span>
                        </div>
                        <input ref={fileInputRef} type="file" multiple disabled={isBusy} accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,text/plain,.doc,.docx,.xls,.xlsx,.ppt,.pptx" onChange={handleFileInput} />
                    </div>
                    {status && <p className="attachment-status attachment-modal-status" role="status" aria-live="polite">{status}</p>}
                </div>
            </div>
        </div>
    );
}

function CatModal({ categories, onSave, onDelete, onClose, closing }) {
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({ label: '', icon: 'fa-folder', color: '#007aff' });

    const addCat = () => { setEditing('new'); setForm({ label: '', icon: 'fa-folder', color: '#007aff' }); };
    const editCat = (cat) => { setEditing(cat.id); setForm({ label: cat.label, icon: cat.icon, color: cat.color }); };

    const saveCat = () => {
        if (!form.label.trim()) return;
        if (editing === 'new') onSave([...categories, { id: 'cat_' + Date.now(), ...form }], TXT.catAdded);
        else onSave(categories.map(c => c.id === editing ? { ...c, ...form } : c), TXT.catUpdated);
        setEditing(null);
    };

    const delCat = (id) => onDelete(categories.filter(c => c.id !== id), id, 'Kateqoriya silindi');

    return (
        <div className={`modal-overlay active ${closing ? 'closing' : ''}`}>
            <div className="modal" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    {editing ? <button onClick={() => setEditing(null)}><i className="fa-solid fa-chevron-left" style={{ marginRight: 4 }}></i>Geri</button> : <span style={{ width: 60 }}></span>}
                    <span className="modal-title">{editing ? (editing === 'new' ? 'Yeni Kateqoriya' : TXT.edit) : 'Kateqoriyalar'}</span>
                    {editing ? <button onClick={saveCat} disabled={!form.label.trim()} style={!form.label.trim() ? { opacity: 0.4 } : {}}>Saxla</button> : <button onClick={onClose}>{TXT.done}</button>}
                </div>
                <div className="modal-body">
                    {editing ? (
                        <div>
                            <div className="cat-preview"><div className="cat-preview-icon" style={{ background: form.color }}><i className={`fa-solid ${form.icon}`}></i></div><span>{form.label.trim() || TXT.catName}</span></div>
                            <div className="form-group"><label className="form-label">Ad</label><input className="form-input" value={form.label} onChange={e => setForm({ ...form, label: e.target.value })} placeholder={TXT.catName} autoFocus onKeyDown={e => { if (e.key === 'Enter') saveCat(); }} /></div>
                            <div className="form-group"><label className="form-label">{TXT.icon}</label><div className="icon-grid">{ICONS.map(icon => <div key={icon} className={`icon-option ${form.icon === icon ? 'sel' : ''}`} onClick={() => setForm({ ...form, icon })}><i className={`fa-solid ${icon}`}></i></div>)}</div></div>
                            <div className="form-group"><label className="form-label">{TXT.color}</label><div className="color-grid">{COLORS.map(color => <div key={color} className={`color-option ${form.color === color ? 'sel' : ''}`} style={{ background: color }} onClick={() => setForm({ ...form, color })}></div>)}</div></div>
                        </div>
                    ) : (
                        <div>
                            <div className="manage-list">{categories.map(cat => <div key={cat.id} className="manage-item" onClick={() => editCat(cat)}><div className="manage-icon" style={{ background: cat.color }}><i className={`fa-solid ${cat.icon}`}></i></div><span className="manage-label">{cat.label}</span><i className="fa-solid fa-chevron-right manage-chevron"></i><button className="manage-del" onClick={e => { e.stopPropagation(); delCat(cat.id); }}><i className="fa-solid fa-trash-can"></i></button></div>)}</div>
                            <div className="manage-add" onClick={addCat}><i className="fa-solid fa-plus"></i>Yeni Kateqoriya</div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function Toast({ msg }) { return <div className={`toast ${msg ? 'show' : ''}`}>{msg}</div>; }

function App() {
    const [user, setUser] = useState(null);
    const [notes, setNotes] = useState([]);
    const [categories, setCategories] = useState([]);
    const [toast, setToast] = useState('');
    const [noteModal, setNoteModal] = useState(null);
    const [attachmentModal, setAttachmentModal] = useState(null);
    const [catModal, setCatModal] = useState(false);
    const [catModalClosing, setCatModalClosing] = useState(false);
    const [noteModalClosing, setNoteModalClosing] = useState(false);
    const [filter, setFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState('undone');
    const [loading, setLoading] = useState(true);
    const [csrfToken, setCsrfToken] = useState('');
    const [isAdmin, setIsAdmin] = useState(false);
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [draggedCategoryId, setDraggedCategoryId] = useState(null);

    const api = (action, body) => {
        const opts = body
            ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'same-origin', cache: 'no-store' }
            : { credentials: 'same-origin', cache: 'no-store' };
        return fetch('functions.php?action=' + action, opts).then(r => r.json());
    };

    const uploadFile = (noteId, file) => {
        const formData = new FormData();
        formData.append('note_id', noteId);
        formData.append('csrf_token', csrfToken);
        formData.append('attachment', file);
        return fetch('functions.php?action=uploadAttachment', { method: 'POST', body: formData }).then(r => r.json());
    };

    const loadData = React.useCallback(() => {
        Promise.all([api('getNotes'), api('getCategories')]).then(([notesData, catsData]) => {
            if (Array.isArray(notesData)) setNotes(notesData);
            if (Array.isArray(catsData)) setCategories(catsData);
        });
    }, []);

    useEffect(() => {
        fetch('functions.php?action=checkSession', { credentials: 'same-origin', cache: 'no-store' }).then(r => r.json()).then(d => {
            if (d.loggedIn) {
                setCsrfToken(d.csrf_token || '');
                setIsAdmin(!!d.isAdmin);
                Promise.all([
                    fetch('functions.php?action=getNotes').then(r => r.json()),
                    fetch('functions.php?action=getCategories').then(r => r.json())
                ]).then(([notesData, catsData]) => {
                    if (Array.isArray(notesData)) setNotes(notesData);
                    if (Array.isArray(catsData)) setCategories(catsData);
                    setUser(true);
                    setLoading(false);
                });
            } else {
                setLoading(false);
            }
        });
    }, []);

    const handleLogin = (token) => {
        setCsrfToken(token);
        setFilter('all');
        setStatusFilter('undone');
        setNoteModal(null);
        setCatModal(false);
        setUser(true);
        fetch('functions.php?action=checkSession', { credentials: 'same-origin', cache: 'no-store' }).then(r => r.json()).then(d => {
            setIsAdmin(!!d.isAdmin);
        }).catch(() => {});
        loadData();
    };

    useEffect(() => { if (user) loadData(); }, [user, loadData]);

    const showToast = (msg) => { setToast(msg); setTimeout(() => setToast(''), 2000); };

    useEffect(() => {
        if (noteModal === null && !catModal && attachmentModal === null) return undefined;

        const body = document.body;
        const scrollY = window.scrollY;
        const previousStyles = {
            overflow: body.style.overflow,
            position: body.style.position,
            top: body.style.top,
            width: body.style.width,
        };

        body.style.overflow = 'hidden';
        body.style.position = 'fixed';
        body.style.top = `-${scrollY}px`;
        body.style.width = '100%';

        return () => {
            body.style.overflow = previousStyles.overflow;
            body.style.position = previousStyles.position;
            body.style.top = previousStyles.top;
            body.style.width = previousStyles.width;
            window.scrollTo(0, scrollY);
        };
    }, [noteModal, catModal, attachmentModal]);

    const persistNote = async (note) => {
        const noteId = note.id || (typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : Date.now() + '_' + Math.random().toString(36).substr(2, 9));
        const now = new Date().toISOString();
        const updated = note.id
            ? notes.map(current => current.id === noteId ? { ...current, content: note.content, category: note.category, updatedAt: now } : current)
            : [{ id: noteId, content: note.content, category: note.category, completed: false, createdAt: now, updatedAt: now, attachments: [] }, ...notes];
        const saved = await api('saveNotes', { notes: updated, csrf_token: csrfToken });
        if (!saved.success) throw new Error(saved.error || TXT.error);

        setNotes(updated);
        return { noteId, updated, isNew: !note.id };
    };

    const uploadAttachments = async (note, files, onProgress) => {
        const persisted = note.id ? null : await persistNote(note);
        const noteId = persisted ? persisted.noteId : note.id;
        const attachments = [];
        const errors = [];

        for (let index = 0; index < files.length; index += 1) {
            try {
                const result = await uploadFile(noteId, files[index]);
                if (!result.success) throw new Error(result.error || TXT.error);
                attachments.push(result.attachment);
            } catch (error) {
                errors.push(`${files[index].name}: ${error.message || TXT.error}`);
            } finally {
                onProgress(index + 1, files.length);
            }
        }

        if (attachments.length > 0) {
            setNotes(current => current.map(currentNote => currentNote.id === noteId
                ? { ...currentNote, attachments: [...(currentNote.attachments || []), ...attachments] }
                : currentNote));
        }

        return { noteId, attachments, errors };
    };

    const uploadPreviewAttachments = async (note, files, onProgress) => {
        const result = await uploadAttachments(note, files, onProgress);
        if (result.attachments.length > 0) {
            setAttachmentModal(current => current?.id === note.id
                ? { ...current, attachments: [...(current.attachments || []), ...result.attachments] }
                : current);
        }
        return result;
    };

    const saveAttachmentOrder = async (noteId, attachments) => {
        const updateAttachments = current => current.map(note => note.id === noteId ? { ...note, attachments } : note);
        setNotes(updateAttachments);
        setAttachmentModal(current => current?.id === noteId ? { ...current, attachments } : current);

        try {
            const result = await api('saveAttachmentOrder', {
                noteId,
                attachmentIds: attachments.map(attachment => attachment.id),
                csrf_token: csrfToken,
            });
            if (!result.success) throw new Error(result.error || TXT.error);
        } catch (error) {
            try {
                const freshNotes = await api('getNotes');
                if (Array.isArray(freshNotes)) {
                    setNotes(freshNotes);
                    setAttachmentModal(current => {
                        const refreshedNote = freshNotes.find(note => note.id === current?.id);
                        return refreshedNote || current;
                    });
                }
            } catch (_) {
                // The client keeps its local order when refreshing the canonical data is unavailable.
            }
            throw error;
        }
    };

    const deletePreviewAttachment = async (noteId, attachmentId) => {
        const result = await api('deleteAttachment', { id: attachmentId, csrf_token: csrfToken });
        if (!result.success) throw new Error(result.error || TXT.error);

        const updateAttachments = current => current.map(note => note.id === noteId
            ? { ...note, attachments: (note.attachments || []).filter(attachment => attachment.id !== attachmentId) }
            : note);
        setNotes(updateAttachments);
        setAttachmentModal(current => current?.id === noteId
            ? { ...current, attachments: (current.attachments || []).filter(attachment => attachment.id !== attachmentId) }
            : current);
    };

    const saveNote = async (note, pendingFiles = [], onProgress = () => {}) => {
        const { noteId } = await persistNote(note);
        const uploadResult = pendingFiles.length > 0
            ? await uploadAttachments({ ...note, id: noteId }, pendingFiles, onProgress)
            : { errors: [] };

        setNoteModal(null);
        showToast(uploadResult.errors.length > 0
            ? `Qeyd yadda saxlandı, ${uploadResult.errors.length} fayl yüklənmədi.`
            : (note.id ? TXT.noteUpdated : TXT.noteAdded));
    };

    const deleteNote = (note) => {
        const updated = notes.filter(n => n.id !== note.id);
        api('saveNotes', { notes: updated, csrf_token: csrfToken }).then(d => {
            if (d.success) {
                setNotes(updated);
                showToast('Qeyd silindi');
            } else showToast(d.error || TXT.error);
        }).catch(() => showToast(TXT.error));
    };

    const toggleDone = (note) => {
        const updated = notes.map(n => n.id === note.id ? { ...n, completed: !n.completed } : n);
        api('saveNotes', { notes: updated, csrf_token: csrfToken }).then(d => {
            if (d.success) setNotes(updated);
            else showToast(d.error || TXT.error);
        }).catch(() => showToast(TXT.error));
    };

    const moveNote = (note, targetNote, placement = 'before') => {
        if (note.category !== targetNote.category || note.id === targetNote.id) return;

        const categoryNotes = notes.filter(currentNote => currentNote.category === note.category);
        const reorderedCategoryNotes = categoryNotes.filter(currentNote => currentNote.id !== note.id);
        const targetPosition = reorderedCategoryNotes.findIndex(currentNote => currentNote.id === targetNote.id);
        if (targetPosition === -1) return;

        const insertPosition = targetPosition + (placement === 'after' ? 1 : 0);
        reorderedCategoryNotes.splice(insertPosition, 0, note);
        let categoryPosition = 0;
        const updated = notes.map(currentNote => {
            if (currentNote.category !== note.category) return currentNote;
            return reorderedCategoryNotes[categoryPosition++];
        });

        api('saveNotes', { notes: updated, csrf_token: csrfToken }).then(d => {
            if (d.success) setNotes(updated);
            else showToast(d.error || TXT.error);
        }).catch(() => showToast(TXT.error));
    };

    const saveCategories = (cats, msg) => {
        setCategories(cats);
        api('saveCategories', { categories: cats, csrf_token: csrfToken }).then(d => {
            if (d.success && msg) showToast(msg);
            else if (!d.success) showToast(d.error || TXT.error);
        }).catch(() => showToast(TXT.error));
    };

    const saveCategoryOrder = (orderedCategories) => {
        setCategories(orderedCategories);
        api('saveCategoryOrder', { categoryIds: orderedCategories.map(cat => cat.id), csrf_token: csrfToken }).then(d => {
            if (d.success) showToast('Kateqoriya sırası yeniləndi');
            else {
                loadData();
                showToast(d.error || TXT.error);
            }
        }).catch(() => {
            loadData();
            showToast(TXT.error);
        });
    };

    const handleCategoryDragStart = (event, categoryId) => {
        const categoryItem = event.currentTarget.closest('.category-nav-item');
        if (categoryItem) {
            const bounds = categoryItem.getBoundingClientRect();
            event.dataTransfer.setDragImage(categoryItem, event.clientX - bounds.left, event.clientY - bounds.top);
        }
        setDraggedCategoryId(categoryId);
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', categoryId);
    };

    const handleCategoryDrop = (event, targetId) => {
        event.preventDefault();
        const sourceId = event.dataTransfer.getData('text/plain') || draggedCategoryId;
        setDraggedCategoryId(null);
        if (!sourceId || sourceId === targetId) return;

        const sourceIndex = categories.findIndex(cat => cat.id === sourceId);
        const targetIndex = categories.findIndex(cat => cat.id === targetId);
        if (sourceIndex === -1 || targetIndex === -1) return;

        const reordered = [...categories];
        const [movedCategory] = reordered.splice(sourceIndex, 1);
        reordered.splice(targetIndex, 0, movedCategory);
        saveCategoryOrder(reordered);
    };

    const deleteCategory = (cats, removedId, msg) => {
        const updatedNotes = notes.map(n => n.category === removedId ? { ...n, category: '' } : n);
        if (updatedNotes.some((n, i) => n !== notes[i])) {
            setNotes(updatedNotes);
            api('saveNotes', { notes: updatedNotes, csrf_token: csrfToken }).catch(() => showToast(TXT.relinkFail));
        }
        if (filter === removedId) setFilter('all');
        saveCategories(cats, msg);
    };

    const logout = () => {
        fetch('functions.php?action=logout', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ csrf_token: csrfToken }) })
            .finally(() => {
                setUser(false);
                setCsrfToken('');
                setIsAdmin(false);
                setNotes([]);
                setCategories([]);
                setFilter('all');
                setStatusFilter('undone');
                setNoteModal(null);
                setCatModal(false);
            });
    };

    const closeNoteModal = () => { setNoteModalClosing(true); setTimeout(() => { setNoteModal(null); setNoteModalClosing(false); }, 250); };
    const closeCatModal = () => { setCatModalClosing(true); setTimeout(() => { setCatModal(false); setCatModalClosing(false); }, 250); };

    const catCount = (catId) => notes.filter(n => n.category === catId && !n.completed).length;
    const allCount = notes.length;

    const visibleNotes = notes.filter(n => {
        const catOk = filter === 'all' || n.category === filter;
        const statusOk = statusFilter === 'all' || (statusFilter === 'done' && n.completed) || (statusFilter === 'undone' && !n.completed);
        return catOk && statusOk;
    });
    const visibleCount = visibleNotes.length;

    useEffect(() => {
        if (filter === 'all') return;
        if (!categories.some(c => c.id === filter)) setFilter('all');
    }, [categories, filter]);

    const filterLabel = filter === 'all' ? TXT.all : (categories.find(c => c.id === filter)?.label || TXT.all);

    const scopeNotes = filter === 'all' ? notes : notes.filter(n => n.category === filter);
    const scopeAll = scopeNotes.length;
    const scopeDone = scopeNotes.filter(n => n.completed).length;
    const scopeUndone = scopeAll - scopeDone;

    const closeMobileMenu = () => setMobileMenuOpen(false);

    const handleNavClick = (catId) => {
        setFilter(catId);
        closeMobileMenu();
    };

    // Disable body scroll when sidebar is open on mobile
    useEffect(() => {
        if (mobileMenuOpen) {
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
            document.body.style.width = '100%';
            document.body.style.height = '100%';
        } else {
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.width = '';
            document.body.style.height = '';
        }
        
        // Cleanup
        return () => {
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.width = '';
            document.body.style.height = '';
        };
    }, [mobileMenuOpen]);

    if (loading) return <div className="login-page"><div className="spinner"></div></div>;
    if (!user) return <Login onLogin={handleLogin} />;

    return (
        <div className="app-layout">
            <div className={`sidebar-overlay ${mobileMenuOpen ? 'active' : ''}`} onClick={closeMobileMenu}></div>
            <aside className={`sidebar ${mobileMenuOpen ? 'mobile-open' : ''}`}>
                <div className="sidebar-header">
                    <button className="mobile-close-btn" onClick={closeMobileMenu}><i className="fa-solid fa-xmark"></i></button>
                </div>
                <nav className="sidebar-nav">
                    <div className="nav-section">
                        <div className="nav-section-title">{TXT.notesNav}</div>
                        <button className={`nav-item ${filter === 'all' ? 'active' : ''}`} onClick={() => handleNavClick('all')}>
                            <span className="icon"><i className="fa-regular fa-note-sticky"></i></span>
                            {TXT.all}
                            <span className="count">{allCount}</span>
                        </button>
                    </div>
                    <div className="nav-section">
                        <div className="nav-section-title">Kateqoriyalar</div>
                        {categories.map(cat => (
                            <button
                                key={cat.id}
                                className={`nav-item category-nav-item ${filter === cat.id ? 'active' : ''} ${draggedCategoryId === cat.id ? 'dragging' : ''}`}
                                title="Üç nöqtədən tutub sıralayın"
                                onDragOver={event => event.preventDefault()}
                                onDrop={event => handleCategoryDrop(event, cat.id)}
                                onClick={() => handleNavClick(cat.id)}
                            >
                                <span
                                    className="category-drag-handle"
                                    draggable="true"
                                    title="Sıralamaq üçün sürüşdürün"
                                    onDragStart={event => handleCategoryDragStart(event, cat.id)}
                                    onDragEnd={() => setDraggedCategoryId(null)}
                                    onClick={event => event.stopPropagation()}
                                ><i className="fa-solid fa-ellipsis-vertical"></i></span>
                                <span className="icon" style={{ color: cat.color }}><i className={`fa-solid ${cat.icon}`}></i></span>
                                {cat.label}
                                <span className="count">{catCount(cat.id)}</span>
                            </button>
                        ))}
                    </div>
                </nav>
                <div className="sidebar-footer">
                    <button onClick={() => setCatModal(true)}><i className="fa-solid fa-gear" style={{ marginRight: 4 }}></i> Kateqoriyalar</button>
                    {isAdmin && <button onClick={() => { window.location.href = 'admin/'; }} style={{ marginTop: 4 }}><i className="fa-solid fa-shield-halved" style={{ marginRight: 4 }}></i> Admin panel</button>}
                    <button onClick={logout} style={{ marginTop: 4 }}><i className="fa-solid fa-right-from-bracket" style={{ marginRight: 4 }}></i> {TXT.logout}</button>
                </div>
            </aside>

            <main className="main-content">
                <div className="toolbar">
                    <div className="toolbar-left">
                    <button className="mobile-menu-btn" onClick={() => setMobileMenuOpen(true)}>
                        <i className="fa-solid fa-bars"></i>
                    </button>
                    <span className="toolbar-title">{filterLabel}</span>
                    <span className="toolbar-count">{visibleCount} qeyd</span>
                </div>
                    <div className="toolbar-right">
                        <div className="status-seg">
                            <button className={statusFilter === 'undone' ? 'active' : ''} onClick={() => setStatusFilter('undone')}>Aktiv <span className="seg-count">{scopeUndone}</span></button>
                            <button className={statusFilter === 'done' ? 'active' : ''} onClick={() => setStatusFilter('done')}>{TXT.finished} <span className="seg-count">{scopeDone}</span></button>
                        </div>
                        <button className="btn primary" disabled={filter === 'all'} title={filter === 'all' ? TXT.pickCategory : ''} onClick={() => { if (filter !== 'all') setNoteModal({ category: filter }); }}><i className="fa-solid fa-plus"></i> Yeni Qeyd</button>
                    </div>
                </div>

                <NoteList notes={notes} categories={categories} catFilter={filter} statusFilter={statusFilter} onDelete={deleteNote} onEdit={setNoteModal} onPreviewAttachments={setAttachmentModal} onToggle={toggleDone} onMove={moveNote} />
                {noteModal !== null && <NoteModal note={noteModal} categories={categories} currentCat={filter} onSave={saveNote} onDeleteAttachment={deletePreviewAttachment} onClose={closeNoteModal} closing={noteModalClosing} />}
                {attachmentModal !== null && <AttachmentModal note={attachmentModal} onClose={() => setAttachmentModal(null)} onUploadAttachments={uploadPreviewAttachments} onSaveOrder={saveAttachmentOrder} onDeleteAttachment={deletePreviewAttachment} />}
                {catModal && <CatModal categories={categories} onSave={saveCategories} onDelete={deleteCategory} onClose={closeCatModal} closing={catModalClosing} />}
                <Toast msg={toast} />
            </main>
        </div>
    );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
