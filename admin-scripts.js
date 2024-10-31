let get_gallery_stats = null

jQuery(document).ready(function($) {

    function populateConvertButton(image, name = '') {
        image.name = image.name || name
        const buttonLabel = image.webp_url ?
            '<span class="dashicons dashicons-update"></span> Reconvert' :
            '<span class="dashicons dashicons-admin-tools"></span> Convert';
        const buttonClass = image.webp_url ? 'converted-button' : 'convert-button';
        return `<button class="${buttonClass} convert" data-name="${image.name}" data-convert="${image.url}" onClick="next_convertfile(this)">${buttonLabel}</button>`;
    }

    function populateImageSizes(image) {
        return Object.entries(image.sizes).map(([size, imgSize]) => `
            <div class='image-size'>
                <div>${size}</div>
                <div>${populateConvertButton(imgSize, image.name + ' @ ' + size)}</div>
            </div>
        `).join('');
    }

    function fetchStats() {

        
        $.post(ajaxurl, { action: 'get_cache_stats' }, function(data) {
            console.log(data);
        });  

        $.post(ajaxurl, { action: 'get_conversion_stats' }, function(data) {
            console.log(data);
            if (data) {

                let statsHtml = `
                    <ul>
                        <li><strong>Original Uploads Directory:</strong> ${data.original_dir}</li>
                        <li><strong>Next Gen Uploads Directory:</strong> ${data.webp_dir}</li>
                        <li><strong>Total Size of Original Uploads Folder:</strong> ${(data.original_size / 1024 / 1024).toFixed(2)} MB</li>
                        <li><strong>Total Size of Next Gen Images:</strong> ${(data.webp_size / 1024 / 1024).toFixed(2)} MB</li>
                         <li><strong>Number of files in Original Uploads:</strong> ${data.original_images_count}</li>
                        <li><strong>Number of Next Gen Files:</strong> ${data.webp_images_count}</li>
                        <li><strong>Percentage of Files Converted:</strong> ${data.percentage_files_converted.toFixed(2)}%</li>
                        <li><strong>Remaining Potential Files to Convert:</strong> ${data.remaining_files_to_convert}</li>
                    </ul>
                `;

                $('#statistics').html(statsHtml);
            } else {
                console.error('No statistics data received');
            }
        });

        

        $.post(ajaxurl, { action: 'get_gallery_stats' }, function(data) {

          
            if (data && data.length > 0) {
                
                get_gallery_stats = prepareConversionStack([...data])

                // Update the "Convert Remaining" button label
                if (get_gallery_stats.length === 0) {
                    $('#convert-all-remaining').text('All Eligible Images Converted').attr('disabled', 'disabled');
                } else {
                    $('#convert-all-remaining').text(`Convert Remaining Wordpress Images (${get_gallery_stats.length})`).removeAttr('disabled');
                }
                 

                const tableBody = $('#sortable-table tbody');
                data.forEach(image => {
                    const imageSizesHtml = populateImageSizes(image);
                    const row = `
                        <tr>
                            <td>${image.name}</td>
                            <td>${image.date || ''}</td>
                            <td><a href="${image.url}" target="_blank"><img loading="lazy" src="${image.url}"/></a></td>
                            <td>
                                <button class="view-sizes" onclick="toggleSizes(this)"><span class="dashicons dashicons-visibility"></span> sizes</button>
                                ${populateConvertButton(image)}
                                <div class="image-sizes hidden">${imageSizesHtml}</div>
                            </td>
                        </tr>`;
                    tableBody.append(row);
                });
                $('#sortable-table').DataTable();
            } else {
                console.error('No gallery data received');
            }
        });
    }


    function prepareConversionStack(images) {
        let conversionStack = [];
    
        images.forEach(image => {
            if (image.url && !image.webp_url) { // Check if main image needs conversion
                conversionStack.push({ url: image.url, name: image.name });
            }
             
            // Check if individual sizes need conversion
            Object.entries(image.sizes).forEach(([key, size]) => {
                if (size.url && !size.webp_url) { 
                    conversionStack.push({ url: size.url, name: `${image.name} @ ${key}` }); // Use ${key} to get the key
                }
            });
        });
    
        return conversionStack;
    }
    
    function processQueue(queue, concurrentTasks, callback) {

        $('#progress-container').show();
        $('#progress-bar').width('0%');

        let activeTasks = 0;
        let stopProcessing = false; // Flag to indicate if processing should be stopped
        const totalTasks = queue.length; // Total number of tasks at the beginning
        const notificationArea = jQuery('.notification-area');
    
        const processNext = () => {
            if (queue.length === 0 && activeTasks === 0) {
                if (!stopProcessing) {

                    // Set progress bar to 100% when done
                    $('#progress-bar').width('100%').text('Done!');
                
                    notificationArea.text("All conversions completed successfully! The page will refresh shortly.").show().addClass('notice notice-success').removeClass('notice-error notice-info');

                    setTimeout(function() {
                        window.location.reload(true); // The true parameter forces the browser to reload from the server, not cache.
                    }, 2000);
                  
                }
                console.log('Conversion process completed: 100%');
                if (typeof callback === "function") {
                    callback(); // Call the callback function after all conversions are done
                }
                return;
            }
    
            if (stopProcessing) {
               
                return; // Stop processing if a failure has occurred
            }
    
            while (queue.length > 0 && activeTasks < concurrentTasks && !stopProcessing) {
                const task = queue.shift(); // Get the next task to process
                activeTasks++;
                const completedTasks = totalTasks - queue.length - activeTasks; // Number of completed tasks
                const percentageCompleted = ((completedTasks / totalTasks) * 100).toFixed(2);
                console.log(`${queue.length} conversion tasks left. Completion: ${percentageCompleted}%`);
                $('#progress-bar').width(percentageCompleted + '%').text(percentageCompleted + '%');

                const nonce = nextgenconvert_params.convert_nonce; // Retrieve the nonce value
                jQuery.post(ajaxurl, { action: 'convert',nonce: nonce, url: task.url }, function(response) {
                    activeTasks--;
                    if (response.success) {
                        notificationArea.html(`Conversion successful for <strong>${task.name}</strong>!`).show().addClass('notice notice-success').removeClass('notice-error notice-info');
                    } else {
                        stopProcessing = true; // Set flag to stop processing further tasks
                        handleError(response,$('#convert-all-remaining') , notificationArea);
                    }
                    processNext(); // Process next task after current one finishes
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    activeTasks--;
                    stopProcessing = true; // Set flag to stop processing further tasks
                    console.error("AJAX Request Failed: ", textStatus, errorThrown);
                    console.error("Response Text: ", jqXHR.responseText);
                    console.error("Status Code: ", jqXHR.status);
                    notificationArea.text(`Error: there is an issue with WordPress while converting ${task.name}`).show().addClass('notice notice-error').removeClass('notice-success notice-info');
                    processNext(); // Process next task even if current one fails
                });
            }
        };
    
        processNext(); // Start processing
    }
    
    
    $('#next #convert-all-remaining').click(function() {
        if (get_gallery_stats && get_gallery_stats.length > 0) {
            const conversionStack = get_gallery_stats; // Prepare the stack of tasks that need conversion
            console.log(`${conversionStack.length} conversion tasks prepared.`);
            $(this).html('Converting ...')
            processQueue(conversionStack, 5, function() {
                $('#sortable-table').DataTable().clear().draw();
                fetchStats()
            }); // Start processing the conversion stack
          
            ;
        } else {
            console.error('No gallery data available');
        }
    });

    $('#next #delete-all').click(function() {
        const notificationArea = jQuery('.notification-area');
        const nonce = nextgenconvert_params.delete_nonce; // Retrieve the nonce value

        jQuery.post(ajaxurl, { action: 'deleteAll', nonce: nonce }, function(response) {
            // Success response handling
            if (response.success) {
                console.log('The directory and its contents were deleted successfully');
                // Update the UI to inform the user
                notificationArea.text('The directory and its contents were deleted successfully.  The page will refresh shortly.').show().addClass('notice-success').removeClass('notice-error');
                // Set a timeout before refreshing the page
                setTimeout(function() {
                    window.location.reload(true); // The true parameter forces the browser to reload from the server, not cache.
                }, 2000);

            } else {
                // Handle failure according to the received message
                console.error('Failed to delete the directory: ', response.data.message);
                // Update the UI to inform the user
                notificationArea.text('Failed to delete the directory: ' + response.data.message).show().addClass('notice-error').removeClass('notice-success');
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            // Network or server error handling
            console.error("AJAX Request Failed: ", textStatus, errorThrown);
            console.error("Response Text: ", jqXHR.responseText);
            console.error("Status Code: ", jqXHR.status);
            // Update the UI to inform the user
            notificationArea.text('Error: Network or server issue.').show().addClass('notice-error').removeClass('notice-success');
        });
    });


    fetchStats();
});

window.toggleSizes = function(button) {
    jQuery(button).parent().find('.image-sizes').toggleClass('hidden');
};

 
function next_convertfile(el) {
    var url = el.getAttribute("data-convert");
    var name = el.getAttribute("data-name");

    var button = jQuery(el); // Get the jQuery object for the button
    var notificationArea = jQuery('.notification-area'); // Notification area

    if (!url) return;
    button.html('<span class="dashicons dashicons-update-alt"></span> Converting...').prop('disabled', true);
    const nonce = nextgenconvert_params.convert_nonce; // Retrieve the nonce value
    jQuery.post(ajaxurl, { action: 'convert',nonce: nonce, url: url }, function(response) {
        if (response.success) {
            //@todo move to css
            button.text('Done').css('background-color', '#dff0d8').prop('disabled', true);
            notificationArea.html("Conversion successful for: <strong>" + name + '</strong>!').show().addClass('notice notice-success').removeClass('notice-error notice-info');
        } else {
            handleError(response, button, notificationArea);
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        console.error("AJAX Request Failed: ", textStatus, errorThrown);
        console.error("Response Text: ", jqXHR.responseText);
        console.error("Status Code: ", jqXHR.status);
        notificationArea.text("Error: there is an issue with WordPress").show().addClass('notice notice-error').removeClass('notice-success notice-info');
        button.html('<span class="dashicons dashicons-admin-tools"></span> Convert').prop('disabled', false);
    }).always(function() {
        jQuery('#convert-all-remaining, #reconvert-all-remaining').prop('disabled', false);
    });
}

 
function handleError(response, button, notificationArea) {
    if (response.code === 429) {
        // Handle rate limit specific logic here
    }
    if (response.code === 500) {
        // Handle error specific logic here
    }
    button.html('<span class="dashicons dashicons-admin-tools"></span> Convert').prop('disabled', false);
    console.log(response)
    notificationArea.text("Error: " + response.data.message).show().addClass('notice notice-error').removeClass('notice-success notice-info');
}