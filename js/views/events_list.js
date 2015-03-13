(function ()
{
    'use strict';

    var height;

    jQuery(document).ready(function ()
    {
        init_list();
        height = jQuery('.main').height();
    });

    function init_list()
    {
        object_name[0].sort(function(a, b)
        {
            var a1 = a.start_order,
                b1 = b.start_order;

            if (a1 == b1)
            {
                return 0;
            }

            return a1 > b1 ? 1 : -1;
        });

        var eventList,
            dates = [];

        var template = '<li><h1 class="main"></h1>' +
                       '<h3 class="name"></h3>' +
                       '<span class="start"></span><br><span class="end"></span>' +
                       '<br><span class="location"></span></li>';

        var paginationTopOptions =
        {
            name: "paginationTop",
            paginationClass: "paginationTop",
            outerWindow: 10
        };

        var paginationBottomOptions =
        {
            name: "paginationBottom",
            paginationClass: "paginationBottom",
            outerWindow: 10
        };

        var options =
        {
            valueNames: ['main', 'name', 'start', 'end', 'location', 'hidden'],
            searchClass: "search",
            item: template,
            page: 25,
            plugins:
            [
                ListPagination(paginationTopOptions),
                ListPagination(paginationBottomOptions)
            ]
        };

        eventList = new List('event-list', options);

        var searchField = eventList.helpers.getByClass(document, 'search', true);
        eventList.helpers.events.bind(searchField, 'keyup', function(e)
        {
            var target = e.target || e.srcElement; // IE have srcElement
            eventList.search(target.value, ['hidden']);
        });

        for (var i = 0; i < object_name[0].length; i++)
        {
            var name = '<a href="' + object_name[1].root + '/' + object_name[0][i].id + '/">' +
                       object_name[0][i].name + '</a>';

            var start_date = '<strong>' + object_name[1].start_text + ':</strong> ' +
                             object_name[0][i].start_at + ' @ ' + object_name[0][i].start_time;

            var end_date = '<strong>' + object_name[1].end_text + ':</strong> ' +
                           object_name[0][i].end_time;

            var location = '<strong>' + object_name[1].location_text + ':</strong> ' +
                           object_name[0][i].location;

            var hidden = object_name[0][i].name;

            if (dates.indexOf(object_name[0][i].start_at) !== -1)
            {
                eventList.add
                ({
                    name: name,
                    start: start_date,
                    end: end_date,
                    location: location,
                    hidden: hidden
                });
            }
            else
            {
                eventList.add
                ({
                    main: object_name[0][i].start_at,
                    name: name,
                    start: start_date,
                    end: end_date,
                    location: location,
                    hidden: hidden
                });
            }

            dates.push(object_name[0][i].start_at);

            /* eventList.filter(function(item)
            {
                if (jQuery(item.values().name).text() == "test")
                {
                   return true;
                }
                else
                {
                   return false;
                }
            }); */
        }
    }

})();