/**
 * BuddyBoss Members Widget
 * Displays a list of BuddyPress members using the BuddyPress REST API
 */
class BBMembersWidget {
    constructor() {
        this.currentType = 'active';
        this.page = 1;
        this.loading = false;
        this.hasMore = true;
        this.nonce = '';  // Will be set from page
        
        this.init();
    }
    
    init() {
        // Try to get nonce from page
        const nonceElement = document.querySelector('input[name="nonce"]');
        if (nonceElement) {
            this.nonce = nonceElement.value;
        }
        
        this.bindEvents();
        this.loadMembers('active');
    }
    
    bindEvents() {
        // Tab switching
        document.querySelectorAll('.bb-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const type = e.currentTarget.dataset.type;
                this.switchTab(type);
            });
        });
        
        // Infinite scroll
        const container = document.getElementById('membersContainer');
        if (container) {
            container.addEventListener('scroll', () => {
                if (container.scrollTop + container.clientHeight >= container.scrollHeight - 100) {
                    this.loadMoreMembers();
                }
            });
        }
    }
    
    switchTab(type) {
        document.querySelectorAll('.bb-tab').forEach(t => t.classList.remove('active'));
        document.querySelector(`[data-type="${type}"]`).classList.add('active');
        
        this.currentType = type;
        this.page = 1;
        this.hasMore = true;
        
        const membersList = document.getElementById('membersList');
        if (membersList) {
            membersList.innerHTML = '';
            this.loadMembers(type);
        }
    }
    
    async loadMembers(type, append = false) {
        if (this.loading || !this.hasMore) return;
        
        this.loading = true;
        const loadingIndicator = document.getElementById('loadingIndicator');
        if (loadingIndicator) {
            loadingIndicator.style.display = 'block';
        }
        
        try {
            let filterBy = type;
            if (type === 'newest') filterBy = 'newest';
            else if (type === 'popular') filterBy = 'popular';
            else filterBy = 'active';
            
            const formData = new FormData();
            formData.append('scope', 'all');
            formData.append('filter', filterBy);
            formData.append('nonce', this.nonce || '');
            formData.append('action', 'members_filter');
            formData.append('object', 'members');
            formData.append('target', '#buddypress [data-bp-list]');
            formData.append('search_terms', '');
            formData.append('page', this.page);
            formData.append('extras', '');
            formData.append('caller', '');
            formData.append('template', '');
            formData.append('method', 'reset');
            formData.append('ajaxload', 'true');
            formData.append('order_by', '');
            
            const response = await fetch(`${window.location.origin}/wp-admin/admin-ajax.php`, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const responseText = await response.text();
            console.log('Response received:', responseText.substring(0, 100) + '...');
            
            // Check if we have a response and extract member data
            if (responseText) {
                // Try to parse as JSON first
                let htmlContent;
                try {
                    const jsonResponse = JSON.parse(responseText);
                    // Extract HTML content from the JSON structure
                    htmlContent = jsonResponse?.data?.contents;
                    console.log('Parsed JSON response, extracting HTML content');
                } catch (e) {
                    // If JSON parsing fails, treat as raw HTML
                    htmlContent = responseText;
                    console.log('Using response as raw HTML');
                }
                
                if (htmlContent) {
                    // Create a temporary div to parse the HTML response
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = htmlContent;
                    
                    // Extract member items from the response - directly use the structure from BuddyPress
                    const memberItems = tempDiv.querySelectorAll('li.item-entry');
                    
                    if (memberItems && memberItems.length > 0) {
                        console.log(`Found ${memberItems.length} members in response`);
                        const members = Array.from(memberItems).map(item => {
                            // Extract member details
                            const avatar = item.querySelector('.item-avatar img')?.src || '';
                            const name = item.querySelector('.list-title.member-name a')?.textContent || 'Unknown Member';
                            const profileUrl = item.querySelector('.list-title.member-name a')?.href || '#';
                            const userId = item.getAttribute('data-bp-item-id') || '';
                            
                            // Extract last activity
                            const activityElement = item.querySelector('.item-meta.last-activity');
                            const activity = activityElement ? activityElement.textContent.trim() : '';
                            
                            // Check online status from member-status element
                            const statusElement = item.querySelector('.member-status');
                            const isOnline = statusElement && statusElement.classList.contains('online');
                            
                            return {
                                id: userId,
                                name: name,
                                avatar: avatar,
                                profileUrl: profileUrl,
                                status: activity,
                                online: isOnline
                            };
                        });
                        
                        this.renderMembers(members, append);
                        this.hasMore = memberItems.length >= 10; // Assume there are more if we got a full page
                        this.page++;
                    } else {
                        // No members found
                        console.log('No member items found in the response');
                        this.hasMore = false;
                        if (!append) {
                            this.renderNoMembers();
                        }
                    }
                } else {
                    console.log('No HTML content found in response');
                    this.hasMore = false;
                    if (!append) {
                        this.renderNoMembers();
                    }
                }
            } else {
                throw new Error('Empty response');
            }
            
        } catch (error) {
            console.error('Error loading members:', error);
            // Fallback to BP REST API
            this.loadMembersFromAPI(type, append);
        } finally {
            this.loading = false;
            if (loadingIndicator) {
                loadingIndicator.style.display = 'none';
            }
        }
    }
    
    async loadMembersFromAPI(type, append = false) {
        try {
            let sortBy = 'active';
            if (type === 'popular') sortBy = 'popular';
            if (type === 'newest') sortBy = 'newest';
            
            const response = await fetch(`${window.location.origin}/wp-json/buddypress/v1/members?type=${sortBy}&page=${this.page}&per_page=10`);
            
            if (!response.ok) {
                throw new Error('BP API response was not ok');
            }
            
            const members = await response.json();
            
            if (members && members.length) {
                const formattedMembers = members.map(member => ({
                    id: member.id,
                    name: member.name,
                    avatar: member.avatar_urls?.full || member.avatar_urls?.thumb,
                    status: this.getActivityStatus(member.last_activity),
                    online: this.isUserOnline(member.last_activity),
                    profileUrl: member.link
                }));
                
                this.renderMembers(formattedMembers, append);
                this.hasMore = members.length === 10;
            } else {
                this.hasMore = false;
                if (!append) {
                    this.renderNoMembers();
                }
            }
            
            this.page++;
            
        } catch (error) {
            console.error('Error loading members from BP API:', error);
            // Last resort - render a message
            if (!append) {
                this.renderNoMembers();
            }
        }
    }
    
    renderNoMembers() {
        const container = document.getElementById('membersList');
        if (container) {
            container.innerHTML = '<div class="bb-no-members">No members found</div>';
        }
    }
    
    isUserOnline(lastActivity) {
        if (!lastActivity) return false;
        
        const activityTime = new Date(lastActivity).getTime();
        const now = new Date().getTime();
        const diff = now - activityTime;
        
        return diff < 15 * 60 * 1000; // 15 minutes
    }
    
    getActivityStatus(lastActivity) {
        if (!lastActivity) return 'No recent activity';
        
        const activityTime = new Date(lastActivity).getTime();
        const now = new Date().getTime();
        const diff = now - activityTime;
        
        if (diff < 5 * 60 * 1000) {
            return 'Active now';
        } else if (diff < 60 * 60 * 1000) {
            return `Active ${Math.floor(diff / (60 * 1000))} min ago`;
        } else if (diff < 24 * 60 * 60 * 1000) {
            return `Active ${Math.floor(diff / (60 * 60 * 1000))} hours ago`;
        } else {
            const date = new Date(lastActivity);
            return `Last seen ${date.toLocaleDateString()}`;
        }
    }
    
    loadMoreMembers() {
        this.loadMembers(this.currentType, true);
    }
    
    renderMembers(members, append = false) {
        const container = document.getElementById('membersList');
        if (!container) {
            console.error('Members list container not found');
            return;
        }
        
        if (!members || !members.length) {
            if (!append) {
                container.innerHTML = '<div class="bb-no-members">No members found</div>';
            }
            return;
        }
        
        console.log(`Rendering ${members.length} members to the container`);
        
        const membersHtml = members.map(member => `
            <a class="bb-member-card" onclick="window.location.href='${member.profileUrl}'" href="${member.profileUrl}">
                <div class="bb-member-avatar-wrap">
                    <img src="${member.avatar}" alt="${member.name}" class="bb-member-avatar" onerror="this.src='${window.location.origin}/wp-content/plugins/buddyboss-platform/bp-core/images/profile-avatar-buddyboss.png'" />
                    <div class="bb-online-indicator ${member.online ? 'pulse' : ''}" style="background: ${member.online ? 'var(--bb-success-color)' : '#cbd5e0'}"></div>
                </div>
                <div class="bb-member-details">
                    <div class="bb-member-name">${member.name}</div>
                    <div class="bb-member-activity">${member.status}</div>
                </div>
            </a>
        `).join('');
        
        if (append) {
            container.innerHTML += membersHtml;
        } else {
            container.innerHTML = membersHtml;
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new BBMembersWidget();
});
