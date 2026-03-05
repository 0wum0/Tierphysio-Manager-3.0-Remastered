/**
 * SwipeToDelete - A pure ES5 module for swipe-to-delete functionality
 * @author Bolt
 */
(function(window) {
    'use strict';

    // Inject required CSS
    var style = document.createElement('style');
    style.textContent = [
        '.swipe-to-delete {',
        '    overflow: hidden;',
        '    position: relative;',
        '    touch-action: pan-y pinch-zoom;',
        '}',
        '.swipe-to-delete li {',
        '    transition: transform 0.3s ease;',
        '    position: relative;',
        '}',
        '.swipe-to-delete li.swiping {',
        '    transition: none;',
        '}',
        '.swipe-to-delete li.removing {',
        '    transition: all 0.5s ease;',
        '    height: 0;',
        '    padding-top: 0;',
        '    padding-bottom: 0;',
        '    margin-top: 0;',
        '    margin-bottom: 0;',
        '    opacity: 0;',
        '}',
        '.swipe-action {',
        '    position: absolute;',
        '    top: 0;',
        '    height: 100%;',
        '    display: flex;',
        '    align-items: center;',
        '    padding: 0 20px;',
        '    color: white;',
        '    font-weight: bold;',
        '    opacity: 0;',
        '    transition: opacity 0.3s ease;',
        '}',
        '.swipe-action.left {',
        '    right: 0;',
        '    background: #dc3545;',
        '}',
        '.swipe-action.right {',
        '    left: 0;',
        '    background: #28a745;',
        '}'
    ].join('\n');
    document.head.appendChild(style);

    /**
     * SwipeToDelete constructor
     * @param {HTMLElement} element - The container element
     * @param {Object} options - Configuration options
     */
    function SwipeToDelete(element, options) {
        this.element = element;
        this.options = {
            threshold: options.threshold || 0.3,
            minSwipeDistance: options.minSwipeDistance || 60,
            actionThreshold: options.actionThreshold || 0.5,
            leftAction: options.leftAction || 'Delete',
            rightAction: options.rightAction || 'Archive',
            onLeftSwipe: options.onLeftSwipe || function() {},
            onRightSwipe: options.onRightSwipe || function() {},
            animationDuration: options.animationDuration || 300
        };

        this.init();
    }

    SwipeToDelete.prototype = {
        init: function() {
            this.element.className += ' swipe-to-delete';
            this.items = this.element.getElementsByTagName('li');
            this.setupItems();
            this.bindEvents();
        },

        setupItems: function() {
            var self = this;
            Array.prototype.forEach.call(this.items, function(item) {
                // Create action elements
                var leftAction = document.createElement('div');
                leftAction.className = 'swipe-action left';
                leftAction.textContent = self.options.leftAction;

                var rightAction = document.createElement('div');
                rightAction.className = 'swipe-action right';
                rightAction.textContent = self.options.rightAction;

                item.appendChild(leftAction);
                item.appendChild(rightAction);
            });
        },

        bindEvents: function() {
            var self = this;
            var startX, currentX, startY, currentY;
            var initialX;
            var currentItem = null;
            var isSwiping = false;

            function handleStart(e) {
                if (currentItem) return;
                
                var touch = e.type === 'mousedown' ? e : e.touches[0];
                startX = touch.pageX;
                startY = touch.pageY;
                currentX = startX;
                currentY = startY;
                
                currentItem = e.target.closest('li');
                if (!currentItem) return;
                
                initialX = 0;
                isSwiping = false;
                
                currentItem.className += ' swiping';
                
                if (e.type === 'mousedown') {
                    document.addEventListener('mousemove', handleMove);
                    document.addEventListener('mouseup', handleEnd);
                }
            }

            function handleMove(e) {
                if (!currentItem) return;
                
                var touch = e.type === 'mousemove' ? e : e.touches[0];
                currentX = touch.pageX;
                currentY = touch.pageY;
                
                // Check if scrolling vertically
                if (!isSwiping) {
                    var deltaX = Math.abs(currentX - startX);
                    var deltaY = Math.abs(currentY - startY);
                    
                    if (deltaX > deltaY && deltaX > 10) {
                        isSwiping = true;
                        e.preventDefault();
                    } else if (deltaY > deltaX) {
                        handleEnd(e);
                        return;
                    }
                }
                
                if (isSwiping) {
                    e.preventDefault();
                    var deltaX = currentX - startX;
                    var transform = deltaX + initialX;
                    
                    currentItem.style.transform = 'translateX(' + transform + 'px)';
                    
                    // Show/hide action buttons
                    var leftAction = currentItem.querySelector('.swipe-action.left');
                    var rightAction = currentItem.querySelector('.swipe-action.right');
                    
                    if (transform < 0) {
                        leftAction.style.opacity = Math.min(Math.abs(transform) / 100, 1);
                        rightAction.style.opacity = 0;
                    } else {
                        rightAction.style.opacity = Math.min(Math.abs(transform) / 100, 1);
                        leftAction.style.opacity = 0;
                    }
                }
            }

            function handleEnd(e) {
                if (!currentItem || !isSwiping) {
                    reset();
                    return;
                }
                
                var deltaX = currentX - startX;
                var threshold = self.options.threshold * currentItem.offsetWidth;
                
                if (Math.abs(deltaX) > threshold) {
                    // Swipe completed
                    var isLeft = deltaX < 0;
                    removeItem(currentItem, isLeft);
                } else {
                    // Reset position
                    currentItem.style.transform = '';
                    currentItem.querySelector('.swipe-action.left').style.opacity = 0;
                    currentItem.querySelector('.swipe-action.right').style.opacity = 0;
                }
                
                reset();
            }

            function reset() {
                if (currentItem) {
                    currentItem.className = currentItem.className.replace(' swiping', '');
                }
                
                currentItem = null;
                isSwiping = false;
                
                document.removeEventListener('mousemove', handleMove);
                document.removeEventListener('mouseup', handleEnd);
            }

            function removeItem(item, isLeft) {
                item.className += ' removing';
                
                var callback = isLeft ? self.options.onLeftSwipe : self.options.onRightSwipe;
                callback(item);
                
                setTimeout(function() {
                    if (item.parentNode) {
                        item.parentNode.removeChild(item);
                    }
                }, self.options.animationDuration);
            }

            // Bind touch events
            this.element.addEventListener('touchstart', handleStart);
            this.element.addEventListener('touchmove', handleMove);
            this.element.addEventListener('touchend', handleEnd);
            this.element.addEventListener('touchcancel', handleEnd);

            // Bind mouse events
            this.element.addEventListener('mousedown', handleStart);
        },

        destroy: function() {
            // Remove all event listeners and clean up
            this.element.className = this.element.className.replace(' swipe-to-delete', '');
            
            // Remove action elements
            var actions = this.element.querySelectorAll('.swipe-action');
            Array.prototype.forEach.call(actions, function(action) {
                if (action.parentNode) {
                    action.parentNode.removeChild(action);
                }
            });

            // Remove inline styles from items
            Array.prototype.forEach.call(this.items, function(item) {
                item.style.transform = '';
                item.className = item.className.replace(' swiping', '').replace(' removing', '');
            });
        }
    };

    // Export to window
    window.SwipeToDelete = SwipeToDelete;
})(window);

var notificationList = document.querySelector('.notification');
var swipeToDelete = new SwipeToDelete(notificationList, {
    threshold: 0.3,
    onLeftSwipe: function(item) {
        console.log('Item deleted:', item);
    },
    onRightSwipe: function(item) {
        console.log('Item archived:', item);
    }
});