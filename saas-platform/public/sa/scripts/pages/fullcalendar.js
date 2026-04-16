/*!
 * FullCalendar Implementation for SmartAdmin WebApp
 * ©2025 SmartAdmin WebApp
 */

// Sample events data
const eventData = [
    { id: '1', title: 'Team Meeting', start: new Date(new Date().setHours(10, 0)), end: new Date(new Date().setHours(11, 30)), backgroundColor: 'var(--primary-500)', borderColor: 'var(--primary-600)', description: 'Weekly team status meeting', location: 'Conference Room A' },
    { id: '2', title: 'Client Call', start: new Date(new Date().setDate(new Date().getDate() + 1)), allDay: true, backgroundColor: 'var(--success-500)', borderColor: 'var(--success-600)', description: 'Quarterly review with major client', location: 'Zoom Meeting' },
    { id: '3', title: 'Product Launch', start: new Date(new Date().setDate(new Date().getDate() + 3)), end: new Date(new Date().setDate(new Date().getDate() + 3)).setHours(15, 0), backgroundColor: 'var(--danger-500)', borderColor: 'var(--danger-600)', description: 'New product launch event', location: 'Main Auditorium' },
    { id: '4', title: 'Deadline: Q3 Report', start: new Date(new Date().setDate(new Date().getDate() + 5)), allDay: true, backgroundColor: 'var(--warning-500)', borderColor: 'var(--warning-600)', description: 'Submit quarterly financial reports', location: 'Finance Department' },
    { id: '5', title: 'Training Session', start: new Date(new Date().setDate(new Date().getDate() - 2)), end: new Date(new Date().setDate(new Date().getDate() - 2)).setHours(16, 0), backgroundColor: 'var(--info-500)', borderColor: 'var(--info-600)', description: 'New software training for all employees', location: 'Training Room B' },
    { id: '6', title: 'Board Meeting', start: new Date(new Date().setDate(new Date().getDate() + 7)), allDay: true, backgroundColor: 'var(--danger-500)', borderColor: 'var(--danger-700)', description: 'Annual board meeting with stakeholders', location: 'Executive Boardroom' },
    { id: '7', title: 'Website Maintenance', start: new Date(new Date().setDate(new Date().getDate() - 1)).setHours(23, 0), end: new Date(new Date().setDate(new Date().getDate())).setHours(5, 0), backgroundColor: 'var(--info-500)', borderColor: 'var(--info-600)', description: 'Scheduled website maintenance window', location: 'IT Department' },
    { id: '8', title: 'Team Building', start: new Date(new Date().setDate(new Date().getDate() + 12)), end: new Date(new Date().setDate(new Date().getDate() + 12)).setHours(16, 0), backgroundColor: 'var(--primary-300)', borderColor: 'var(--primary-400)', description: 'Annual team building activities', location: 'City Park' },
    { id: '9', title: 'Client Dinner', start: new Date(new Date().setDate(new Date().getDate() + 4)).setHours(19, 0), end: new Date(new Date().setDate(new Date().getDate() + 4)).setHours(21, 0), backgroundColor: 'var(--success-300)', borderColor: 'var(--success-400)', description: 'Dinner with potential investors', location: 'Downtown Restaurant' },
    { id: '10', title: 'Vacation', start: new Date(new Date().setDate(new Date().getDate() + 14)), end: new Date(new Date().setDate(new Date().getDate() + 21)), backgroundColor: 'var(--bs-teal)', borderColor: 'var(--bs-teal)', description: 'Annual vacation time', location: 'Beach Resort' },
    { id: '11', title: 'Marketing Campaign', start: new Date(new Date().setDate(new Date().getDate() + 2)), end: new Date(new Date().setDate(new Date().getDate() + 2)).setHours(17, 0), backgroundColor: 'var(--bs-purple)', borderColor: 'var(--bs-purple)', description: 'Launch new product marketing campaign', location: 'Marketing Department', category: 'marketing' },
    { id: '12', title: 'Sales Meeting', start: new Date(new Date().setDate(new Date().getDate() + 3)).setHours(9, 0), end: new Date(new Date().setDate(new Date().getDate() + 3)).setHours(10, 30), backgroundColor: 'var(--success-600)', borderColor: 'var(--success-700)', description: 'Monthly sales team catchup', location: 'Conference Room B', category: 'sales' },
    { id: '13', title: 'Development Sprint Review', start: new Date(new Date().setDate(new Date().getDate() + 6)).setHours(14, 0), end: new Date(new Date().setDate(new Date().getDate() + 6)).setHours(16, 0), backgroundColor: 'var(--bs-indigo)', borderColor: 'var(--bs-indigo)', description: 'End of sprint review with stakeholders', location: 'Dev Team Area', category: 'development' },
    { id: '14', title: 'Tech Conference', start: new Date(new Date().setDate(new Date().getDate() + 8)), end: new Date(new Date().setDate(new Date().getDate() + 10)), backgroundColor: 'var(--bs-indigo)', borderColor: 'var(--bs-indigo)', description: 'Annual tech conference for industry professionals', location: 'Convention Center', category: 'conference' },
    { id: '15', title: 'Dentist Appointment', start: new Date(new Date().setDate(new Date().getDate() + 2)).setHours(14, 0), end: new Date(new Date().setDate(new Date().getDate() + 2)).setHours(15, 0), backgroundColor: 'var(--warning-500)', borderColor: 'var(--warning-600)', description: 'Routine dental checkup', location: 'Downtown Clinic', category: 'personal' },
    { id: '16', title: 'Project Milestone: Beta Release', start: new Date(new Date().setDate(new Date().getDate() + 15)), allDay: true, backgroundColor: 'var(--success-500)', borderColor: 'var(--success-600)', description: 'Beta release of the new app', location: 'Development Team', category: 'development' },
    { id: '17', title: 'Company Announcement', start: new Date(new Date().setDate(new Date().getDate() + 1)).setHours(11, 0), end: new Date(new Date().setDate(new Date().getDate() + 1)).setHours(11, 30), backgroundColor: 'var(--primary-500)', borderColor: 'var(--primary-600)', description: 'Announcement of new company policies', location: 'Main Hall', category: 'announcement' },
    { id: '18', title: 'Weekly Team Sync', start: new Date(new Date().setDate(new Date().getDate() + 4)).setHours(9, 0), end: new Date(new Date().setDate(new Date().getDate() + 4)).setHours(9, 30), backgroundColor: 'var(--primary-400)', borderColor: 'var(--primary-500)', description: 'Recurring weekly sync for project updates', location: 'Meeting Room C', category: 'team', extendedProps: { recurrence: 'weekly' } }
];

// Define fixed event categories
const eventCategories = [
    { id: 'general', name: 'General', color: 'var(--primary-500)' },
    { id: 'meeting', name: 'Meetings', color: 'var(--success-500)' },
    { id: 'task', name: 'Tasks', color: 'var(--warning-500)' },
    { id: 'deadline', name: 'Deadlines', color: 'var(--danger-500)' },
    { id: 'marketing', name: 'Marketing', color: 'var(--bs-purple)' },
    { id: 'sales', name: 'Sales', color: 'var(--success-600)' },
    { id: 'development', name: 'Development', color: 'var(--bs-indigo)' }
];

// Initialize calendar after all scripts are loaded
document.addEventListener('DOMContentLoaded', function () {
    // Initialize event modals
    const eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    const categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));

    // Form element references
    const eventForm = document.getElementById('eventForm');
    const eventTitle = document.getElementById('eventTitle');
    const eventStart = document.getElementById('eventStart');
    const eventEnd = document.getElementById('eventEnd');
    const eventAllDay = document.getElementById('eventAllDay');
    const eventColor = document.getElementById('eventColor');
    const eventDescription = document.getElementById('eventDescription');
    const eventLocation = document.getElementById('eventLocation');
    const eventCategory = document.getElementById('eventCategory');
    const eventId = document.getElementById('eventId');
    const deleteId = document.getElementById('deleteEventId');

    // Populate category select dropdown
    populateCategoryDropdown();

    // Pre-process events to ensure all have a category
    eventData.forEach(event => {
        // Initialize extendedProps if it doesn't exist
        if (!event.extendedProps) {
            event.extendedProps = {};
        }

        // Set category in extendedProps if missing
        if (!event.extendedProps.category) {
            event.extendedProps.category = event.category || 'general';
        }
    });

    // Initialize the calendar
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        themeSystem: 'bootstrap',
        headerToolbar: {
            right: 'today prev,next',
            left: 'title'
        },
        events: [],
        // Apply custom styling after render
        datesSet: function () {
            setTimeout(() => {
                // Replace fc-button-group with btn-group
                document.querySelectorAll('.fc-button-group').forEach((el) => {
                    el.classList.remove('fc-button-group');
                    el.classList.add('btn-group');
                });

                // Optional: Replace fc-button styles with Bootstrap
                document.querySelectorAll('.fc-button').forEach((btn) => {
                    btn.classList.remove('fc-button');
                    btn.classList.add('btn', 'btn-outline-default', 'btn-sm');
                });

                 document.querySelectorAll('.fc-button-primary').forEach((btn) => {
                    btn.classList.remove('fc-button-primary');
                });

                // Highlight active button
                const activeBtn = document.querySelector('.fc-button-active');
                if (activeBtn) {
                    activeBtn.classList.remove('fc-button-active');
                    activeBtn.classList.add('active');
                }

                const groupBtn = document.querySelector('.fc-button-group');
                if (groupBtn) {
                    groupBtn.className = 'btn-group';
                }

                const prevBtn = document.querySelector('.fc-prev-button');
                if (prevBtn) {
                    prevBtn.className = 'btn btn-outline-default btn-sm';
                }

                const nextBtn = document.querySelector('.fc-next-button');
                if (nextBtn) {
                    nextBtn.className = 'btn btn-outline-default btn-sm';
                }

                const todayBtn = document.querySelector('.fc-today-button');
                if (todayBtn) {
                    todayBtn.className = 'btn btn-outline-default btn-sm';
                }
            }, 0);
        },

        footerToolbar: {
            left: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        buttonText: {
            today: 'Today',
            month: 'Month',
            week: 'Week',
            day: 'Day',
            list: 'List'
        },
        dayMaxEvents: 2, // Show only 2 events per day, then show "more" link
        height: undefined, // Let the calendar calculate its own height
        minHeight: '750px', // Set minimum height for desktop
        editable: true,
        droppable: true, // allow draggable events
        selectable: true, // allow selection for new events
        businessHours: {
            daysOfWeek: [1, 2, 3, 4, 5], // Monday - Friday
            startTime: '9:00',
            endTime: '17:00'
        },
        eventSources: [
            { events: eventData }
        ],
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short'
        },
        // Handle date selection for new events
        select: function (info) {
            // Reset form
            eventForm.reset();
            eventId.value = '';

            // Set initial form values from selection
            const startDate = info.start;
            const endDate = info.end;

            eventStart.value = formatDateForInput(startDate);
            eventEnd.value = formatDateForInput(endDate);
            eventAllDay.checked = info.allDay;
            eventColor.value = 'var(--primary-500)';

            // Show the modal
            document.getElementById('eventModalTitle').textContent = 'Add New Event';
            document.getElementById('deleteEvent').style.display = 'none';
            eventModal.show();
        },
        // Handle event click for editing
        eventClick: function (info) {
            const event = info.event;
            console.log("Clicked event:", event); // Debug

            // Populate form with event data
            eventId.value = event.id;
            eventTitle.value = event.title;
            eventStart.value = formatDateForInput(event.start);
            eventEnd.value = event.end ? formatDateForInput(event.end) : '';
            eventAllDay.checked = event.allDay;
            eventColor.value = event.backgroundColor || 'var(--primary-500)';

            // Make sure extendedProps is defined before accessing properties
            if (event.extendedProps) {
                eventDescription.value = event.extendedProps.description || '';
                eventLocation.value = event.extendedProps.location || '';
                eventCategory.value = event.extendedProps.category || '';

                console.log("Event category:", event.extendedProps.category); // Debug
            } else {
                eventDescription.value = '';
                eventLocation.value = '';
                eventCategory.value = '';
            }

            // Show the modal
            document.getElementById('eventModalTitle').textContent = 'Edit Event';
            document.getElementById('deleteEvent').style.display = 'block';
            eventModal.show();
        },
        // Allow event dragging and resizing
        eventDrop: function (info) {
            showToast('Event moved: ' + info.event.title);
        },
        eventResize: function (info) {
            showToast('Event duration changed: ' + info.event.title);
        },
        // Handle external draggables
        drop: function (info) {
            // Remove the dragged element from the list if the checkbox is checked
            if (document.getElementById('removeAfterDrop').checked) {
                info.draggedEl.parentNode.removeChild(info.draggedEl);
            }
            showToast('New event added via drag & drop');
        },
        // Add hover tooltips to events
        eventDidMount: function (info) {
            const event = info.event;

            // Create tooltip with rich content
            if (event.extendedProps.description || event.extendedProps.location) {
                const tooltipContent = document.createElement('div');
                tooltipContent.classList.add('fc-event-tooltip');

                const titleEl = document.createElement('div');
                titleEl.classList.add('tooltip-title');
                titleEl.textContent = event.title;
                tooltipContent.appendChild(titleEl);

                if (event.extendedProps.description) {
                    const descEl = document.createElement('div');
                    descEl.classList.add('tooltip-desc');
                    descEl.textContent = event.extendedProps.description;
                    tooltipContent.appendChild(descEl);
                }

                if (event.extendedProps.location) {
                    const locEl = document.createElement('div');
                    locEl.classList.add('tooltip-location');
                    locEl.innerHTML = '<i class="fa fa-map-marker-alt me-1"></i> ' + event.extendedProps.location;
                    tooltipContent.appendChild(locEl);
                }

                // Show time for non-all-day events
                if (!event.allDay && event.start) {
                    const timeEl = document.createElement('div');
                    timeEl.classList.add('tooltip-time');
                    timeEl.innerHTML = '<i class="fa fa-clock me-1"></i> ' +
                        event.start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    if (event.end) {
                        timeEl.innerHTML += ' - ' + event.end.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    }
                    tooltipContent.appendChild(timeEl);
                }

                // Initialize Bootstrap tooltip
                new bootstrap.Tooltip(info.el, {
                    title: tooltipContent.outerHTML,
                    placement: 'top',
                    trigger: 'hover',
                    html: true,
                    container: 'body'
                });
            }
        }
    });

    // First initialize the calendar, then setup filters
    calendar.render();

    // Set up filter controls after calendar initialization
    setupFilterControls();

    // Initialize draggable events
    const draggableEl = document.getElementById('external-events');
    new FullCalendar.Draggable(draggableEl, {
        itemSelector: '.external-event',
        eventData: function (eventEl) {
            return {
                title: eventEl.innerText.trim(),
                backgroundColor: window.getComputedStyle(eventEl).backgroundColor,
                borderColor: window.getComputedStyle(eventEl).borderColor,
                description: eventEl.dataset.description || '',
                location: eventEl.dataset.location || '',
                category: eventEl.dataset.category || ''
            };
        }
    });

    // Handle form submission
    eventForm.addEventListener('submit', function (e) {
        e.preventDefault();

        // Prepare event data
        const eventData = {
            title: eventTitle.value,
            start: new Date(eventStart.value),
            end: eventEnd.value ? new Date(eventEnd.value) : null,
            allDay: eventAllDay.checked,
            backgroundColor: eventColor.value,
            borderColor: eventColor.value,
            extendedProps: {
                description: eventDescription.value,
                location: eventLocation.value,
                category: eventCategory.value || 'general'
            }
        };

        // If a category is selected, use its color
        if (eventCategory.value) {
            const category = eventCategories.find(cat => cat.id === eventCategory.value);
            if (category) {
                eventData.backgroundColor = category.color;
                eventData.borderColor = category.color;
            }
        }

        console.log("Form submitted with data:", eventData); // Debug
        console.log("Event ID:", eventId.value); // Debug

        if (eventId.value) {
            // Update existing event
            const existingEvent = calendar.getEventById(eventId.value);
            console.log("Existing event:", existingEvent); // Debug

            if (existingEvent) {
                // Remove the old event and add a new one to ensure all properties are updated
                existingEvent.remove();

                // Add the updated event with the same ID
                eventData.id = eventId.value;
                calendar.addEvent(eventData);

                showToast('Event updated successfully');
            } else {
                console.error("Could not find event with ID:", eventId.value);
                showToast('Error updating event: Event not found');
            }
        } else {
            // Create new event
            eventData.id = 'event-' + new Date().getTime();
            calendar.addEvent(eventData);
            showToast('Event added successfully');
        }

        // Close the modal
        eventModal.hide();

        // Refresh filters to ensure visibility
        filterEvents();
    });

    // Handle delete button click
    const deleteEventBtn = document.getElementById('deleteEvent');
    if (deleteEventBtn) {
        deleteEventBtn.addEventListener('click', function () {
            deleteId.value = eventId.value;
            eventModal.hide();
            deleteModal.show();
        });
    }

    // Handle confirm delete
    const confirmDeleteBtn = document.getElementById('confirmDelete');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            const event = calendar.getEventById(deleteId.value);
            if (event) {
                event.remove();
                showToast('Event deleted successfully');
            }
            deleteModal.hide();
        });
    }

    // Handle category management modal
    const manageCategoriesBtn = document.getElementById('manageCategoriesBtn');
    if (manageCategoriesBtn) {
        manageCategoriesBtn.addEventListener('click', function () {
            populateCategoryList();
            categoryModal.show();
        });
    }

    // Handle category form submission
    const categoryForm = document.getElementById('categoryForm');
    if (categoryForm) {
        categoryForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const categoryName = document.getElementById('categoryName').value;
            const categoryColor = document.getElementById('categoryColor').value;

            // Simple ID generation
            const categoryId = 'cat-' + new Date().getTime();

            // Add to categories
            eventCategories.push({
                id: categoryId,
                name: categoryName,
                color: categoryColor
            });

            // Update dropdowns
            populateCategoryDropdown();
            populateCategoryList();

            showToast('Category added successfully');
            document.getElementById('categoryName').value = '';
        });
    }

    // Toggle all category filters
    const toggleAllFiltersBtn = document.getElementById('toggleAllFilters');
    if (toggleAllFiltersBtn) {
        toggleAllFiltersBtn.addEventListener('click', function () {
            const checkboxes = document.querySelectorAll('.category-filter');
            // Check if all are currently checked
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);

            // Toggle all checkboxes to the opposite state
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });

            // Update icon based on new state
            const icon = this.querySelector('i');
            if (icon) {
                icon.className = !allChecked ? 'fa fa-check-square' : 'fa fa-square';
            }

            // Trigger filtering
            filterEvents();

            showToast(!allChecked ? 'All categories enabled' : 'All categories disabled');
        });
    }

    // Filter events when checkboxes change
    const categoryFilters = document.querySelectorAll('.category-filter');
    if (categoryFilters && categoryFilters.length > 0) {
        categoryFilters.forEach(checkbox => {
            checkbox.addEventListener('change', filterEvents);
        });
    }

    // Helper functions

    // Format dates for input elements
    function formatDateForInput(date) {
        if (!date) return '';

        const localDate = new Date(date);

        // Format: YYYY-MM-DDTHH:MM
        const year = localDate.getFullYear();
        const month = String(localDate.getMonth() + 1).padStart(2, '0');
        const day = String(localDate.getDate()).padStart(2, '0');
        const hours = String(localDate.getHours()).padStart(2, '0');
        const minutes = String(localDate.getMinutes()).padStart(2, '0');

        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    // Show toast notifications
    function showToast(message) {
        const toastContainer = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = 'toast align-items-center show text-white bg-primary border-0';
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');

        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

        toastContainer.appendChild(toast);

        // Auto-remove after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Populate category dropdown in event form
    function populateCategoryDropdown() {
        const select = document.getElementById('eventCategory');
        select.innerHTML = '<option value="">Select Category</option>';

        eventCategories.forEach(category => {
            const option = document.createElement('option');
            option.value = category.id;
            option.textContent = category.name;
            option.style.color = category.color;
            select.appendChild(option);
        });
    }

    // Populate category list in management modal
    function populateCategoryList() {
        const list = document.getElementById('categoryList');
        list.innerHTML = '';

        eventCategories.forEach((category, index) => {
            // Don't allow deletion of default categories (first 7)
            const isDefaultCategory = index < 7;

            const item = document.createElement('li');
            item.className = 'list-group-item d-flex align-items-center justify-content-between';

            item.innerHTML = `
                <div class="d-flex align-items-center">
                    <span class="badge d-block rounded-circle p-1" style="background-color: ${category.color};"></span>
                    <span class="ms-2">${category.name}</span>
                </div>
                ${!isDefaultCategory ?
                    `<button type="button" class="btn btn-xs btn-outline-danger delete-category" data-id="${category.id}">
                        Delete
                    </button>` :
                    ''}
            `;

            list.appendChild(item);
        });

        // Add event listeners to delete buttons
        document.querySelectorAll('.delete-category').forEach(button => {
            button.addEventListener('click', function () {
                const categoryId = this.getAttribute('data-id');
                deleteCategory(categoryId);
            });
        });
    }

    // Delete a category
    function deleteCategory(categoryId) {
        // Find the index of the category
        const index = eventCategories.findIndex(cat => cat.id === categoryId);
        if (index === -1 || index < 7) return; // Don't delete default categories

        // Get the category name for the confirmation message
        const categoryName = eventCategories[index].name;

        // Remove the category
        eventCategories.splice(index, 1);

        // Update the UI
        populateCategoryDropdown();
        populateCategoryList();
        setupFilterControls();

        // Update events that used this category
        calendar.getEvents().forEach(event => {
            if (event.extendedProps.category === categoryId) {
                event.setExtendedProp('category', 'general');
                event.setProp('backgroundColor', eventCategories[0].color);
                event.setProp('borderColor', eventCategories[0].color);
            }
        });

        showToast(`Category "${categoryName}" deleted`);
    }

    // Set up filter controls
    function setupFilterControls() {
        const container = document.getElementById('category-filters');
        if (!container) return;

        container.innerHTML = '';

        eventCategories.forEach(category => {
            const div = document.createElement('div');
            div.className = 'form-check';

            div.innerHTML = `
                <input class="form-check-input category-filter" type="checkbox" value="${category.id}" id="filter-${category.id}">
                <label class="form-check-label d-flex align-items-center" for="filter-${category.id}">
                    <span class="badge rounded-circle p-1 me-2" style="background-color: ${category.color}; width: 0.25rem; height: 0.25rem;">&nbsp;</span>
                    ${category.name}
                </label>
            `;

            container.appendChild(div);
        });

        // Add event listeners to checkboxes after they're created
        document.querySelectorAll('.category-filter').forEach(checkbox => {
            checkbox.addEventListener('change', filterEvents);
        });
    }

    // Filter events based on category checkboxes
    function filterEvents() {
        if (!calendar) return; // Prevent execution if calendar is not initialized

        const selectedCategories = [];
        document.querySelectorAll('.category-filter:checked').forEach(checkbox => {
            selectedCategories.push(checkbox.value);
        });

        console.log("Selected categories:", selectedCategories); // For debugging

        // If general is selected, make sure any uncategorized events show up
        const includeGeneral = selectedCategories.includes('general');

        // Handle all events, including those without a category
        calendar.getEvents().forEach(event => {
            // Get the event's category, defaulting to 'general' if not set
            let eventCategory = event.extendedProps && event.extendedProps.category ?
                event.extendedProps.category : 'general';

            console.log(`Event: ${event.title}, Category: ${eventCategory}`); // For debugging

            // Show all events if no categories are selected OR the event's category is selected
            // OR if the event has no category and general is selected
            if (selectedCategories.length === 0 ||
                selectedCategories.includes(eventCategory) ||
                (eventCategory === 'general' && includeGeneral)) {
                event.setProp('display', 'auto');
            } else {
                event.setProp('display', 'none');
            }
        });

        // Try both update methods to ensure rendering
        calendar.updateSize();
    }

    // Export events as JSON
    const exportEventsBtn = document.getElementById('exportEvents');
    if (exportEventsBtn) {
        exportEventsBtn.addEventListener('click', function () {
            const events = calendar.getEvents().map(event => {
                return {
                    id: event.id,
                    title: event.title,
                    start: event.start,
                    end: event.end,
                    allDay: event.allDay,
                    backgroundColor: event.backgroundColor,
                    borderColor: event.borderColor,
                    description: event.extendedProps.description,
                    location: event.extendedProps.location,
                    category: event.extendedProps.category
                };
            });

            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(events, null, 2));
            const downloadAnchor = document.createElement('a');
            downloadAnchor.setAttribute("href", dataStr);
            downloadAnchor.setAttribute("download", "calendar-events.json");
            document.body.appendChild(downloadAnchor);
            downloadAnchor.click();
            downloadAnchor.remove();
        });
    }

    // Import events from JSON
    const importEventsBtn = document.getElementById('importEvents');
    if (importEventsBtn) {
        importEventsBtn.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    const events = JSON.parse(e.target.result);

                    // Remove current events
                    calendar.getEvents().forEach(event => event.remove());

                    // Add imported events
                    events.forEach(event => {
                        calendar.addEvent({
                            id: event.id || 'imported-' + new Date().getTime() + Math.random().toString(36).substr(2, 5),
                            title: event.title,
                            start: new Date(event.start),
                            end: event.end ? new Date(event.end) : null,
                            allDay: event.allDay,
                            backgroundColor: event.backgroundColor,
                            borderColor: event.borderColor,
                            extendedProps: {
                                description: event.description,
                                location: event.location,
                                category: event.category
                            }
                        });
                    });

                    showToast('Events imported successfully');
                } catch (err) {
                    showToast('Error importing events: ' + err.message);
                }
            };
            reader.readAsText(file);
        });
    }

    // Print calendar button
    const printCalendarBtn = document.getElementById('printCalendar');
    if (printCalendarBtn) {
        printCalendarBtn.addEventListener('click', function () {
            window.print();
        });
    }
}); 