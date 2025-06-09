// File: elmira_radio/assets/js/main.js

document.addEventListener('DOMContentLoaded', function() {
    // --- Menu Toggle ---
    const menuToggle = document.querySelector('.menu-toggle');
    const navUl = document.querySelector('header nav ul'); 

    if (menuToggle && navUl) {
        menuToggle.addEventListener('click', function() {
            navUl.classList.toggle('show');
            const icon = menuToggle.querySelector('i');
            const isExpanded = navUl.classList.contains('show');
            menuToggle.setAttribute('aria-expanded', isExpanded.toString());
            if (isExpanded) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    }
    
    // --- Audio player functionality (Streaming Page) ---
    const audioPlayer = document.getElementById('radio-stream');
    const playBtn = document.getElementById('play-btn'); 
    const muteBtn = document.getElementById('mute-btn'); 
    const volumeControl = document.getElementById('volume-control'); 
    
    let currentSongFilePath = ''; 

    if (audioPlayer && playBtn) { 
        playBtn.addEventListener('click', function() { 
            if (audioPlayer.paused) {
                console.log("[PlayBtn] Tombol Play diklik. Src saat ini:", audioPlayer.currentSrc, "Ready state:", audioPlayer.readyState); 
                if (currentSongFilePath && audioPlayer.currentSrc !== currentSongFilePath && !audioPlayer.currentSrc.endsWith(currentSongFilePath)) {
                    console.log("[PlayBtn] Mengatur ulang src player ke:", currentSongFilePath); 
                    audioPlayer.src = currentSongFilePath;
                }

                if (!audioPlayer.currentSrc || audioPlayer.currentSrc === '' || audioPlayer.currentSrc === window.location.href) { 
                    console.warn("[PlayBtn] Sumber audio kosong atau menunjuk ke halaman itu sendiri. Mencoba mengambil info now playing dulu..."); 
                    fetchNowPlayingData(true); 
                } else {
                    console.log("[PlayBtn] Mencoba memutar sumber yang ada:", audioPlayer.currentSrc); 
                    audioPlayer.play().then(() => {
                        console.log("[PlayBtn] Pemutaran berhasil dimulai untuk sumber yang ada."); 
                    }).catch(error => {
                        console.error("[PlayBtn] Error saat memutar audio via tombol play:", error.name, error.message, error); 
                        if (error.name === 'NotSupportedError' || error.name === 'AbortError') {
                             console.warn("[PlayBtn] Error play, mencoba mengambil ulang data Now Playing..."); 
                             fetchNowPlayingData(true);
                        }
                    });
                }
            } else {
                audioPlayer.pause(); 
                console.log("[PlayBtn] Audio dijeda oleh tombol."); 
            }
        });
        
        audioPlayer.addEventListener('play', function() { 
            if(playBtn) playBtn.innerHTML = '<i class="fas fa-pause"></i>'; 
            console.log("[Event:play] Pemutaran dimulai. Waktu saat ini:", audioPlayer.currentTime); 
        });
        audioPlayer.addEventListener('pause', function() { 
            if(playBtn) playBtn.innerHTML = '<i class="fas fa-play"></i>'; 
            console.log("[Event:pause] Pemutaran dijeda. Waktu saat ini:", audioPlayer.currentTime); 
        });
        audioPlayer.addEventListener('ended', function() { 
            console.log("[Event:ended] Pemutaran audio berakhir. Waktu saat ini:", audioPlayer.currentTime); 
            if(playBtn) playBtn.innerHTML = '<i class="fas fa-play"></i>'; 
            console.log("[Event:ended] Lagu berakhir, mengambil info terbaru."); 
            fetchNowPlayingData(true); 
        });
        audioPlayer.addEventListener('error', function(e) { 
            console.error("[Event:error] Event error pada audio player terpicu."); 
            let error = e.target.error;
            let errorMessage = "Error audio tidak diketahui.";
            if (error) {
                switch (error.code) {
                    case MediaError.MEDIA_ERR_ABORTED: 
                        errorMessage = "Pemutaran dibatalkan oleh pengguna atau skrip."; 
                        break;
                    case MediaError.MEDIA_ERR_NETWORK: 
                        errorMessage = "Kesalahan jaringan menyebabkan unduhan audio gagal sebagian."; 
                        break;
                    case MediaError.MEDIA_ERR_DECODE: 
                        errorMessage = "Pemutaran dibatalkan karena masalah korupsi atau karena media menggunakan fitur yang tidak didukung browser Anda."; 
                        break;
                    case MediaError.MEDIA_ERR_SRC_NOT_SUPPORTED: 
                        errorMessage = "Audio tidak dapat dimuat, baik karena server atau jaringan gagal atau karena formatnya tidak didukung."; 
                        break;
                    default:
                        errorMessage = `Terjadi kesalahan yang tidak diketahui (Kode: ${error.code}).`; 
                }
                console.error("MediaError Code:", error.code, "Message:", errorMessage); 
                console.error("Full MediaError object:", error); 
            }
            console.error("Sumber audio saat error:", audioPlayer.currentSrc); 
            if (playBtn) playBtn.innerHTML = '<i class="fas fa-play"></i>'; 
        });
         audioPlayer.addEventListener('loadedmetadata', function() { 
            console.log("[Event:loadedmetadata] Metadata audio dimuat. Durasi:", audioPlayer.duration, "ReadyState:", audioPlayer.readyState); 
        });
        audioPlayer.addEventListener('canplay', function() { 
            console.log("[Event:canplay] Audio bisa diputar. ReadyState:", audioPlayer.readyState); 
        });
        audioPlayer.addEventListener('stalled', function() { 
            console.warn("[Event:stalled] Audio Stalled: Browser mencoba mendapatkan data media, tetapi data tidak tersedia. Src:", audioPlayer.currentSrc, "ReadyState:", audioPlayer.readyState, "NetworkState:", audioPlayer.networkState); 
        });
        audioPlayer.addEventListener('waiting', function() { 
            console.log("[Event:waiting] Audio Waiting: Pemutaran berhenti karena kekurangan data (buffering). Src:", audioPlayer.currentSrc, "ReadyState:", audioPlayer.readyState); 
        });
        audioPlayer.addEventListener('suspend', function() { 
            console.log("[Event:suspend] Audio Suspend: Pemuatan data media ditangguhkan. Src:", audioPlayer.currentSrc, "NetworkState:", audioPlayer.networkState); 
        });
    }
    
    if (audioPlayer && muteBtn && volumeControl) { 
        muteBtn.addEventListener('click', function() { 
            audioPlayer.muted = !audioPlayer.muted; 
            if (audioPlayer.muted) {
                muteBtn.innerHTML = '<i class="fas fa-volume-mute"></i>'; 
                volumeControl.value = 0; 
            } else {
                muteBtn.innerHTML = '<i class="fas fa-volume-up"></i>'; 
                volumeControl.value = audioPlayer.volume; 
            }
        });
        volumeControl.addEventListener('input', function() { 
            audioPlayer.volume = parseFloat(this.value); 
             if (audioPlayer.volume > 0) {
                audioPlayer.muted = false; 
                muteBtn.innerHTML = '<i class="fas fa-volume-up"></i>'; 
            } else if (audioPlayer.volume === 0 && !audioPlayer.muted) {
                 muteBtn.innerHTML = '<i class="fas fa-volume-off"></i>'; 
            } else { 
                muteBtn.innerHTML = '<i class="fas fa-volume-mute"></i>'; 
            }
        });
    }

    // --- Update now playing info (Streaming Page & Index Page) ---
    const streamingAudioPlayer = document.getElementById('radio-stream'); 

    function updateNowPlayingDisplay(songData, attemptPlay = false) { 
        const streamTitle = document.getElementById('now-playing-title'); 
        const streamArtist = document.getElementById('now-playing-artist'); 
        const streamAlbum = document.getElementById('now-playing-album'); 
        const streamCover = document.getElementById('now-playing-cover'); 
        const streamPenyiar = document.getElementById('np-penyiar-name'); 

        const mainTitle = document.getElementById('main-now-playing-title'); 
        const mainArtist = document.getElementById('main-now-playing-artist'); 
        const mainCover = document.getElementById('main-now-playing-cover'); 

        const defaultTitle = "ELMIRA 95.8 FM"; 
        const defaultArtist = "Musik Terbaik Untuk Anda"; 
        const defaultCoverPath = (typeof basePath !== 'undefined' ? basePath : '') + '/assets/images/logo.png'; 

        if (songData) { 
            if (streamTitle) streamTitle.textContent = songData.song_title || defaultTitle; 
            if (streamArtist) streamArtist.textContent = songData.artist_name || defaultArtist; 
            if (streamAlbum) { 
                // streamAlbum.textContent = songData.album || ''; // Jika ingin menampilkan nama album
                // streamAlbum.style.display = songData.album ? 'block' : 'none'; 
            }
            if (streamCover) streamCover.src = songData.album_art_url || defaultCoverPath; 
            if (streamPenyiar) streamPenyiar.textContent = songData.penyiar_username || 'Elmira FM'; 

            if (mainTitle) mainTitle.textContent = songData.song_title || defaultTitle; 
            if (mainArtist) mainArtist.textContent = songData.artist_name || defaultArtist; 
            if (mainCover) mainCover.src = songData.album_art_url || defaultCoverPath; 

            if (streamingAudioPlayer && songData.song_file_path) { 
                const newSrc = songData.song_file_path; 
                
                if (currentSongFilePath !== newSrc || streamingAudioPlayer.src === '' || streamingAudioPlayer.src === window.location.href || attemptPlay) {
                    console.log(`[UpdateDisplay] Mengubah sumber audio ke: ${newSrc}. AttemptPlay: ${attemptPlay}`); 
                    currentSongFilePath = newSrc; 
                    streamingAudioPlayer.src = newSrc; 
                    streamingAudioPlayer.load(); 
                    
                    if (attemptPlay) { 
                        console.log("[UpdateDisplay] Mencoba memutar sumber baru karena attemptPlay=true."); 
                        streamingAudioPlayer.play().then(() => {
                            console.log("[UpdateDisplay] Pemutaran audio berhasil dimulai untuk sumber baru."); 
                        }).catch(e => {
                            console.error("[UpdateDisplay] Error auto-playing sumber baru:", e.name, e.message, e); 
                            if (playBtn) playBtn.innerHTML = '<i class="fas fa-play"></i>'; 
                        });
                    }
                }
            } else if (streamingAudioPlayer && !songData.song_file_path) {
                 console.warn("[UpdateDisplay] Tidak ada path file lagu spesifik. Player source tidak diubah."); 
                 if (currentSongFilePath !== '' || streamingAudioPlayer.currentSrc !== '') {
                    console.log("[UpdateDisplay] Menghentikan player karena tidak ada lagu aktif."); 
                    streamingAudioPlayer.pause();
                    streamingAudioPlayer.src = ''; 
                    currentSongFilePath = '';
                 }
            }
        } else { // Tidak ada songData
            if (streamTitle) streamTitle.textContent = defaultTitle; 
            if (streamArtist) streamArtist.textContent = defaultArtist; 
            if (streamAlbum) streamAlbum.style.display = 'none'; 
            if (streamCover) streamCover.src = defaultCoverPath; 
            if (streamPenyiar) streamPenyiar.textContent = 'Elmira FM'; 

            if (mainTitle) mainTitle.textContent = defaultTitle; 
            if (mainArtist) mainArtist.textContent = defaultArtist; 
            if (mainCover) mainCover.src = defaultCoverPath; 
            
            if (streamingAudioPlayer) {
                console.warn("[UpdateDisplay] Tidak ada data lagu. Menampilkan default."); 
                if (currentSongFilePath !== '' || streamingAudioPlayer.currentSrc !== '') {
                    console.log("[UpdateDisplay] Menghentikan player karena tidak ada data lagu."); 
                    streamingAudioPlayer.pause();
                    streamingAudioPlayer.src = '';
                    currentSongFilePath = '';
                 }
            }
        }
    }
    
    function fetchNowPlayingData(attemptPlayAfterFetch = false) { 
        if (!document.getElementById('now-playing-title') && 
            !document.getElementById('main-now-playing-title') &&
            !streamingAudioPlayer) { 
            return; 
        }
        console.log(`[FetchData] Mengambil info now playing... (attemptPlayAfterFetch: ${attemptPlayAfterFetch})`); 
        
        fetch(basePath + '/includes/auth.php?action=now_playing_info') 
        .then(response => {
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.status} ${response.statusText}`); 
            }
            return response.json(); 
        })
        .then(data => {
            console.log("[FetchData] Menerima data:", data); 
            if (data.success && data.song) { 
                updateNowPlayingDisplay(data.song, attemptPlayAfterFetch); 
            } else {
                console.warn("[FetchData] Gagal mendapatkan data lagu yang valid:", data.message || "Tidak ada data lagu."); 
                updateNowPlayingDisplay(null, attemptPlayAfterFetch); 
            }
        }).catch(error => {
            console.error('[FetchData] Error mengambil info now playing:', error); 
            updateNowPlayingDisplay(null, attemptPlayAfterFetch); 
        });
    }

    if (document.getElementById('now-playing-title') || 
        document.getElementById('main-now-playing-title') ||
        streamingAudioPlayer) {
        fetchNowPlayingData(false); 
        setInterval(() => fetchNowPlayingData(false), 15000);  
        console.log("Initial fetchNowPlayingData dipanggil. Interval fetching diaktifkan."); 
    }

    // --- Penyiar Dashboard: Update Now Playing Form ---
    const updateNowPlayingForm = document.getElementById('update-now-playing-form'); 
    const npFormFeedbackDiv = document.getElementById('np-form-feedback'); 

    if (updateNowPlayingForm && npFormFeedbackDiv) { 
        updateNowPlayingForm.addEventListener('submit', function(e) { 
            e.preventDefault(); 
            const formData = new FormData(this); 
            const submitButton = document.getElementById('submit-now-playing-btn'); 
            const originalButtonText = submitButton.innerHTML; 

            submitButton.disabled = true; 
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengupdate...'; 
            npFormFeedbackDiv.innerHTML = ''; 

            fetch(basePath + '/includes/auth.php', { 
                method: 'POST', 
                body: formData 
            })
            .then(response => response.json()) 
            .then(data => {
                if (data.success) { 
                    npFormFeedbackDiv.innerHTML = `<div class="alert alert-success">${data.message || 'Info "Now Playing" berhasil diperbarui!'}</div>`; 
                    fetchNowPlayingData(true); // Coba putar lagu baru setelah update
                } else {
                    npFormFeedbackDiv.innerHTML = `<div class="alert alert-error">${data.message || 'Gagal memperbarui info.'}</div>`; 
                }
            })
            .catch(error => {
                console.error('Error updating now playing:', error); 
                npFormFeedbackDiv.innerHTML = '<div class="alert alert-error">Terjadi kesalahan koneksi.</div>'; 
            })
            .finally(() => {
                submitButton.disabled = false; 
                submitButton.innerHTML = originalButtonText; 
                setTimeout(() => { npFormFeedbackDiv.innerHTML = ''; }, 7000); 
            });
        });
    }
    
    // --- Live chat functionality ---
    const chatMessagesContainer = document.getElementById('chat-messages') || document.getElementById('penyiar-chat-messages');  
    const chatInput = document.getElementById('chat-message-input') || document.getElementById('penyiar-chat-message-input');  
    const sendMessageBtn = document.getElementById('send-chat-message-btn') || document.getElementById('penyiar-send-chat-btn');  
    let lastChatMessageId = 0;  

    function appendChatMessage(chatData) {  
        if (!chatMessagesContainer) return;
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message';
        messageDiv.dataset.messageId = chatData.id; 

        let usernameDisplay = chatData.username; 

        messageDiv.innerHTML = `
            <span class="username">${usernameDisplay}:</span>
            <span class="message-text">${chatData.message}</span>
            <span class="message-time">${chatData.time}</span>
        `;
        chatMessagesContainer.appendChild(messageDiv);
        if (chatMessagesContainer.scrollHeight - chatMessagesContainer.scrollTop < chatMessagesContainer.clientHeight + 200) { 
            chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;
        }
    }

    if (sendMessageBtn && chatInput && chatMessagesContainer) {
        function sendChatMessageInternal() { 
            const message = chatInput.value.trim();
            if (message === '') return;
            
            const formData = new FormData();
            formData.append('action', 'send_chat_message'); 
            formData.append('message', message); 
            if (chatInput.id === 'penyiar-chat-message-input' && typeof currentProgramIdForChat !== 'undefined' && currentProgramIdForChat) { 
                formData.append('program_id', currentProgramIdForChat); 
            }

            const originalButtonHTML = sendMessageBtn.innerHTML; 
            sendMessageBtn.disabled = true; 
            sendMessageBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; 

            fetch(basePath + '/includes/auth.php', { 
                method: 'POST', 
                body: formData 
            })
            .then(response => response.json()) 
            .then(data => {
                if (data.success && data.chat_message) { 
                    chatInput.value = ''; 
                    fetchChatMessages(); 
                } else {
                    showCustomAlert('Gagal mengirim pesan: ' + (data.message || 'Error tidak diketahui'), 'Chat Error');
                }
            }).catch(error => {
                console.error('Error sending chat message:', error); 
                showCustomAlert('Terjadi kesalahan koneksi saat mengirim pesan.', 'Chat Error'); 
            }).finally(() => {
                sendMessageBtn.disabled = false; 
                sendMessageBtn.innerHTML = originalButtonHTML; 
            });
        }
        sendMessageBtn.addEventListener('click', sendChatMessageInternal); 
        chatInput.addEventListener('keypress', function(e) { 
            if (e.key === 'Enter') { 
                e.preventDefault(); 
                sendChatMessageInternal(); 
            }
        });
        
        function fetchChatMessages() { 
            if (!chatMessagesContainer) return;
            
            let fetchUrl = `${basePath}/includes/auth.php?action=get_chat_messages&limit=50&last_message_id=${lastChatMessageId}`; 
            if (chatMessagesContainer.id === 'penyiar-chat-messages' && typeof currentProgramIdForChat !== 'undefined' && currentProgramIdForChat) {
                fetchUrl += `&program_id_chat=${currentProgramIdForChat}`; 
            }

            fetch(fetchUrl) 
            .then(response => response.json()) 
            .then(data => {
                if (data.success && data.messages) { 
                    const isFirstLoadOrEmpty = chatMessagesContainer.innerHTML.includes("Memuat pesan chat...") || 
                                             (chatMessagesContainer.children.length === 1 && chatMessagesContainer.firstElementChild.classList.contains('login-prompt'));
                    
                    if (isFirstLoadOrEmpty && data.messages.length > 0) {
                        chatMessagesContainer.innerHTML = ''; 
                    }

                    if (data.messages.length > 0) { 
                        data.messages.forEach(msg => { 
                            appendChatMessage(msg); 
                            lastChatMessageId = Math.max(lastChatMessageId, msg.id); 
                        });
                    } else if (isFirstLoadOrEmpty) { 
                        chatMessagesContainer.innerHTML = '<p class="login-prompt" style="text-align:center;">Belum ada pesan.</p>'; 
                    }
                } else if (!data.success) {
                    console.warn("Gagal mengambil pesan chat:", data.message); 
                }
            }).catch(error => console.error('Error fetching chat messages:', error)); 
        }
        
        if ((typeof isUserCurrentlyLoggedIn !== 'undefined' && isUserCurrentlyLoggedIn) || (chatMessagesContainer && chatMessagesContainer.id === 'penyiar-chat-messages')) { 
             fetchChatMessages(); 
             setInterval(fetchChatMessages, 5000); 
        } else if (chatMessagesContainer && !chatMessagesContainer.innerHTML.includes("login untuk mengirim pesan")) { 
             chatMessagesContainer.innerHTML = '<p class="login-prompt" style="text-align:center;">Silakan <a href="' + basePath + '/login.php">login</a> untuk melihat dan mengirim pesan.</p>';
        }
    }

    // --- Song request form (streaming.php) ---
    const streamingRequestForm = document.getElementById('streaming-request-form');  
    const streamingRequestFeedbackDiv = document.getElementById('streaming-request-feedback'); 

    if (streamingRequestForm && streamingRequestFeedbackDiv) {  
        streamingRequestForm.addEventListener('submit', function(e) { 
            e.preventDefault(); 
            
            const songTitle = document.getElementById('streaming-song-title').value.trim(); 
            const artist = document.getElementById('streaming-artist').value.trim(); 
            
            if (songTitle === '' || artist === '') { 
                streamingRequestFeedbackDiv.innerHTML = `<div class="alert alert-error">Judul lagu dan artis tidak boleh kosong.</div>`;
                setTimeout(() => { streamingRequestFeedbackDiv.innerHTML = ''; }, 3000);
                return;
            }

            const formData = new FormData(this);  
            formData.append('action', 'request_song'); 

            const submitButton = this.querySelector('button[type="submit"]'); 
            const originalButtonText = submitButton.innerHTML; 
            submitButton.disabled = true; 
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...'; 
            streamingRequestFeedbackDiv.innerHTML = ''; 

            fetch(basePath + '/includes/auth.php', { 
                method: 'POST', 
                body: formData 
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    return response.text().then(text => { 
                        throw new Error("Respons server bukan JSON: " + text);
                    });
                }
            })
            .then(data => {
                if (data.success) { 
                    streamingRequestFeedbackDiv.innerHTML = `<div class="alert alert-success">${data.message || 'Request berhasil!'}</div>`; 
                    streamingRequestForm.reset(); 
                } else {
                    streamingRequestFeedbackDiv.innerHTML = `<div class="alert alert-error">${data.message || 'Gagal mengirim request.'}</div>`; 
                }
            }).catch(error => {
                console.error('Error submitting song request (streaming):', error); 
                streamingRequestFeedbackDiv.innerHTML = `<div class="alert alert-error">Terjadi kesalahan: ${error.message}</div>`; 
            }).finally(() => {
                 submitButton.disabled = false; 
                 submitButton.innerHTML = originalButtonText; 
                 setTimeout(() => { streamingRequestFeedbackDiv.innerHTML = ''; }, 7000); 
            });
        });
    }
    
    // --- Song request form (request.php) ---
    const pageRequestForm = document.getElementById('song-request-page-form');
    const pageRequestFeedbackDiv = document.getElementById('request-message-container');

    if (pageRequestForm && pageRequestFeedbackDiv) {
        pageRequestForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const songTitle = this.elements['song_title'].value.trim();
            const artist = this.elements['artist'].value.trim();

            if (songTitle === '' || artist === '') {
                pageRequestFeedbackDiv.innerHTML = `<div class="alert alert-error">Judul lagu dan artis tidak boleh kosong.</div>`;
                setTimeout(() => { pageRequestFeedbackDiv.innerHTML = ''; }, 3000);
                return;
            }

            const formData = new FormData(this);
            formData.append('action', 'request_song');
            const submitButton = this.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
            pageRequestFeedbackDiv.innerHTML = ''; // Bersihkan pesan sebelumnya

            fetch(basePath + '/includes/auth.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        throw new Error("Respons server bukan JSON: " + text);
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    pageRequestFeedbackDiv.innerHTML = `<div class="alert alert-success">${data.message || 'Request berhasil dikirim!'}</div>`;
                    pageRequestForm.reset();
                    // Optionally, update history table on this page too
                    if (typeof updateUserRequestHistory === 'function') { // Jika ada fungsi untuk update riwayat
                         updateUserRequestHistory();
                    }
                } else {
                    pageRequestFeedbackDiv.innerHTML = `<div class="alert alert-error">${data.message || 'Gagal mengirim request.'}</div>`;
                }
            })
            .catch(error => {
                console.error('Error submitting song request (request page):', error);
                pageRequestFeedbackDiv.innerHTML = `<div class="alert alert-error">Terjadi kesalahan: ${error.message}</div>`;
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
                setTimeout(() => { if(pageRequestFeedbackDiv) pageRequestFeedbackDiv.innerHTML = ''; }, 7000);
            });
        });
    }

    // --- Penyiar Dashboard: Auto-fill song title from file name ---
    const songFileDropdown = document.getElementById('np_song_file');  
    const songTitleInput = document.getElementById('np_song_title');  

    if (songFileDropdown && songTitleInput) {  
        songFileDropdown.addEventListener('change', function() { 
            if (this.value && (songTitleInput.value.trim() === '' || this.dataset.autofilled === 'true')) { 
                let rawFileName = this.value; 
                let title = pathinfo(rawFileName, 'filename'); 
                if (!title) { 
                    title = rawFileName.substring(0, rawFileName.lastIndexOf('.')) || rawFileName;
                }
                title = title.replace(/[_-]/g, ' ').replace(/\s+/g, ' ').trim(); // Ganti _ atau - dengan spasi, hapus spasi ganda
                title = title.replace(/\b\w/g, char => char.toUpperCase()); 
                songTitleInput.value = title; 
                this.dataset.autofilled = 'true';  
            } else if (!this.value) { 
                 this.dataset.autofilled = 'false';  
            }
        });
        songTitleInput.addEventListener('input', function() { 
            if (songFileDropdown) songFileDropdown.dataset.autofilled = 'false'; 
        });
    }
    
    function pathinfo(path, option) {
        if (typeof path !== 'string') return null;
        let M = path.match(/(.*?\/)?(([^/]*?)(\.([^./]*))?)$/);
        if (!M) return null;
        if (option === 'dirname') return M[1] || '';
        if (option === 'basename') return M[2] || '';
        if (option === 'extension') return M[5] || '';
        if (option === 'filename') return M[3] || '';
        return M[0];
    }

    // --- Penyiar Dashboard: Song Request Actions ---
    const songRequestsTable = document.getElementById('song-requests-table');  
    if (songRequestsTable) {  
        songRequestsTable.addEventListener('click', function(e) { 
            const targetButton = e.target.closest('.action-request-btn'); 
            if (!targetButton) return;

            const row = targetButton.closest('tr'); 
            const requestId = row.dataset.requestId; 
            const status = targetButton.dataset.status; 

            if (!requestId || !status) return;

            const formData = new FormData(); 
            formData.append('action', 'update_request_status');  
            formData.append('request_id', requestId); 
            formData.append('status', status); 
            
            const originalButtonHTML = targetButton.innerHTML; 
            targetButton.disabled = true; 
            targetButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; 

            fetch(basePath + '/includes/auth.php', {  
                method: 'POST', 
                body: formData 
            })
            .then(response => response.json()) 
            .then(data => {
                if (data.success) { 
                    row.style.opacity = '0.3';  
                    const actionsCell = targetButton.closest('td'); 
                    actionsCell.innerHTML = `<span class="status-${status}" style="font-weight:bold; text-transform:capitalize;">${status}</span>`; 
                } else {
                    showCustomAlert('Gagal: ' + (data.message || 'Error tidak diketahui'), 'Request Error'); 
                    targetButton.disabled = false; 
                    targetButton.innerHTML = originalButtonHTML; 
                }
            }).catch(error => {
                console.error('Error updating request status:', error); 
                showCustomAlert('Terjadi kesalahan koneksi.', 'Request Error'); 
                targetButton.disabled = false; 
                targetButton.innerHTML = originalButtonHTML; 
            });
        });
    }

    // --- Password Toggle Visibility ---
    const togglePasswordButtons = document.querySelectorAll('.toggle-password');
    togglePasswordButtons.forEach(button => {
        button.addEventListener('click', function () {
            const targetSelector = this.getAttribute('toggle') || (this.dataset.target ? '#' + this.dataset.target : null) ;
            if (targetSelector) {
                const passwordInput = document.querySelector(targetSelector);
                if (passwordInput) {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                }
            }
        });
    });

    // --- Fungsi untuk Custom Alert Modal ---
    function showCustomAlert(message, title = "Pemberitahuan", callbackOnOk) {
        const modalOverlay = document.getElementById('custom-alert-modal');
        const alertTitle = document.getElementById('custom-alert-title');
        const alertMessage = document.getElementById('custom-alert-message');
        const okButton = document.getElementById('custom-alert-ok-btn');

        if (!modalOverlay || !alertMessage || !okButton || !alertTitle) {
            console.error('Elemen modal kustom tidak ditemukan! Menggunakan alert standar.');
            alert((title !== "Pemberitahuan" ? title + ": " : "") + message);
            if (typeof callbackOnOk === 'function') {
                callbackOnOk();
            }
            return;
        }

        alertTitle.textContent = title;
        alertMessage.textContent = message;
        modalOverlay.classList.add('show');

        const newOkButton = okButton.cloneNode(true);
        okButton.parentNode.replaceChild(newOkButton, okButton);
        
        newOkButton.addEventListener('click', function() {
            modalOverlay.classList.remove('show');
            if (typeof callbackOnOk === 'function') {
                callbackOnOk();
            }
        }, { once: true }); // Pastikan event listener hanya sekali jalan per show
        newOkButton.focus();
    }


    // --- Login & Register Form Submission (AJAX) ---
    const registerForm = document.getElementById('register-form'); 
    // const loginForm = document.getElementById('login-form'); // login.php punya script sendiri

    if (registerForm) { 
        const feedbackDiv = document.getElementById('register-feedback'); 
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault(); 
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            if (feedbackDiv) feedbackDiv.innerHTML = '';

            if (password.length < 6) {
                const msg = 'Password minimal harus 6 karakter!';
                if (feedbackDiv) feedbackDiv.innerHTML = `<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> ${msg}</div>`;
                else showCustomAlert(msg, 'Validasi Gagal');
                return;
            }

            if (password !== confirmPassword) {
                const msg = 'Password dan konfirmasi password tidak sama!';
                if (feedbackDiv) feedbackDiv.innerHTML = `<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> ${msg}</div>`;
                else showCustomAlert(msg, 'Validasi Gagal');
                return;
            }

            const formData = new FormData(this);
            handleAuthFormSubmit(formData, feedbackDiv, this.querySelector('button[type="submit"]'), 'register');
        });
    }

    // Fungsi generik untuk submit form auth
    let authDataHolder = {}; // Untuk menyimpan data response agar bisa diakses di .finally
    function handleAuthFormSubmit(formData, feedbackDiv, submitButton, formType = 'general') {
        const originalButtonText = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
        if (feedbackDiv) feedbackDiv.innerHTML = '';
        authDataHolder = {}; // Reset

        fetch(basePath + '/includes/auth.php', { 
            method: 'POST',
            body: formData
        })
        .then(response => {
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                return response.json();
            } else {
                return response.text().then(text => { 
                    throw new Error("Respons server bukan JSON: " + text);
                });
            }
        })
        .then(data => {
            authDataHolder = data; // Simpan data response
            if (data.success) {
                if (formType === 'register') {
                    showCustomAlert(data.message || 'Registrasi berhasil! Silakan login.', 'Registrasi Berhasil', function() {
                        if (registerForm) registerForm.reset();
                        window.location.href = basePath + '/login.php?success=' + encodeURIComponent(data.message || 'Registrasi berhasil! Silakan login.');
                    });
                } else if (data.redirect_url) { 
                    if (feedbackDiv) feedbackDiv.innerHTML = `<div class="alert alert-success">${data.message || 'Sukses!'} Mengarahkan...</div>`;
                     // Untuk login, tidak perlu modal kustom, langsung redirect.
                    window.location.href = data.redirect_url;
                } else {
                    if (feedbackDiv) feedbackDiv.innerHTML = `<div class="alert alert-success">${data.message || 'Aksi berhasil!'}</div>`;
                    showCustomAlert(data.message || 'Aksi berhasil!', 'Berhasil');
                    setTimeout(() => { if (feedbackDiv) feedbackDiv.innerHTML = ''; }, 3000);
                }
            } else { 
                const errorMsg = data.message || 'Terjadi kesalahan.';
                if (feedbackDiv) feedbackDiv.innerHTML = `<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> ${errorMsg}</div>`;
                else showCustomAlert(errorMsg, 'Error');
            }
        })
        .catch(error => {
            console.error('Auth form error:', error);
            const errorMsg = `Terjadi kesalahan koneksi atau respons server tidak valid: ${error.message}`;
            if (feedbackDiv) feedbackDiv.innerHTML = `<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> ${errorMsg}</div>`;
            else showCustomAlert(errorMsg, 'Error Koneksi');
        })
        .finally(() => {
            let shouldReEnableButton = true;
            if (formType === 'register' && authDataHolder.success) {
                shouldReEnableButton = false; 
            } else if (authDataHolder.success && authDataHolder.redirect_url) {
                 shouldReEnableButton = false; 
            }

            if(shouldReEnableButton){
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        });
    }
    
    // --- Schedule Page Filter ---
    const scheduleFilterButtons = document.querySelectorAll('.schedule-filter .filter-btn');
    const programCards = document.querySelectorAll('.schedule-grid .program-card');
    const scheduleDayHeaders = document.querySelectorAll('.schedule-grid .schedule-day-header');

    if (scheduleFilterButtons.length > 0 && (programCards.length > 0 || scheduleDayHeaders.length > 0)) {
        scheduleFilterButtons.forEach(button => {
            button.addEventListener('click', function() {
                scheduleFilterButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                const filterDay = this.dataset.day.toLowerCase(); 

                programCards.forEach(card => {
                    const cardDay = card.dataset.day.toLowerCase(); 
                    if (filterDay === 'all' || cardDay === filterDay) {
                        card.style.display = 'flex'; 
                    } else {
                        card.style.display = 'none';
                    }
                });

                scheduleDayHeaders.forEach(header => {
                    const headerDayGroup = header.dataset.dayGroup.toLowerCase(); 
                    if (filterDay === 'all') {
                        header.style.display = 'block'; 
                    } else if (headerDayGroup === filterDay) {
                        let programsInThisGroupVisible = false;
                        programCards.forEach(card => {
                            if (card.dataset.day.toLowerCase() === headerDayGroup && card.style.display !== 'none') {
                                programsInThisGroupVisible = true;
                            }
                        });
                        header.style.display = programsInThisGroupVisible ? 'block' : 'none';
                    } else {
                        header.style.display = 'none';
                    }
                });
            });
        });
        const initialActiveFilter = document.querySelector('.schedule-filter .filter-btn.active');
        if (initialActiveFilter) {
            initialActiveFilter.click();
        } else if (scheduleFilterButtons.length > 0) {
            scheduleFilterButtons[0].click(); 
        }
    }

    // --- Program Reminder Button ---
    document.body.addEventListener('click', function(e) {
        const reminderButton = e.target.closest('.program-reminder-btn');
        if (reminderButton) {
            e.preventDefault(); 

            if (typeof isUserCurrentlyLoggedIn === 'undefined' || typeof basePath === 'undefined') {
                console.error('Variabel basePath atau isUserCurrentlyLoggedIn tidak terdefinisi.');
                showCustomAlert('Terjadi kesalahan konfigurasi pada halaman.', 'Error');
                return;
            }

            if (!isUserCurrentlyLoggedIn) {
                showCustomAlert('Silakan login untuk mengatur pengingat.', 'Perlu Login', function() {
                    window.location.href = basePath + '/login.php';
                });
                return;
            }

            const programId = reminderButton.dataset.programId;
            if (!programId) {
                console.error('Program ID tidak ditemukan pada tombol pengingat.');
                return;
            }

            const originalButtonHTML = reminderButton.innerHTML;
            reminderButton.disabled = true;
            reminderButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';

            const formData = new FormData();
            formData.append('action', 'set_program_reminder');
            formData.append('program_id', programId);

            let jsonDataReminder = {}; // Untuk menyimpan data JSON respons agar bisa diakses di .finally

            fetch(basePath + '/includes/auth.php', { 
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                jsonDataReminder = data;
                if (data.success) {
                    if (data.button_text) {
                        reminderButton.innerHTML = data.button_text;
                    }
                    showCustomAlert(data.message || 'Status pengingat diperbarui!', 'Pengingat');
                } else {
                    showCustomAlert('Gagal mengatur pengingat: ' + (data.message || 'Error tidak diketahui.'), 'Error Pengingat');
                    reminderButton.innerHTML = originalButtonHTML; 
                }
            })
            .catch(error => {
                console.error('Error setting program reminder:', error);
                showCustomAlert('Terjadi kesalahan koneksi saat mengatur pengingat.', 'Error Koneksi');
                reminderButton.innerHTML = originalButtonHTML; 
            })
            .finally(() => {
                reminderButton.disabled = false;
                if (!jsonDataReminder.success || !jsonDataReminder.button_text) {
                    if (!jsonDataReminder.success && reminderButton.innerHTML.includes('fa-spinner')) {
                         reminderButton.innerHTML = originalButtonHTML;
                    }
                }
            });
        }
    });
    // Di dalam file: elmira_radio/assets/js/main.js

    function appendChatMessage(chatData) {  
        if (!chatMessagesContainer) return;
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message';
        messageDiv.dataset.messageId = chatData.id; 

        let usernameDisplay = chatData.username;
        let usernameClass = 'username'; // Kelas default

        // Tambahkan kelas berdasarkan user_role
        if (chatData.user_role === 'penyiar') {
            usernameClass += ' username-penyiar';
        } else if (chatData.user_role === 'admin') {
            usernameClass += ' username-admin'; // Jika Anda ingin admin juga beda
        } else {
            usernameClass += ' username-pendengar'; // Untuk user biasa/pendengar
        }

        messageDiv.innerHTML = `
            <span class="${usernameClass}">${usernameDisplay}:</span>
            <span class="message-text">${chatData.message}</span>
            <span class="message-time">${chatData.time}</span>
        `;
        chatMessagesContainer.appendChild(messageDiv);
        if (chatMessagesContainer.scrollHeight - chatMessagesContainer.scrollTop < chatMessagesContainer.clientHeight + 200) { 
            chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;
        }
    }
// ...

}); // Akhir DOMContentLoaded