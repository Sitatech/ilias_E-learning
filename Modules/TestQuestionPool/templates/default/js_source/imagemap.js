var a_polygons;
var a_rects;
var a_circles;

function getPolygons()
{
	allpolys = new Array();
	polys = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'poly') ? true : false; }, 'td', document);
	for (i = 0; i < polys.length; i++)
	{
		children = polys[i].childNodes;
		for (j = 0; j < children.length; j++)
		{
			p = new Array();
			coords = children[j].nodeValue;
			coords = coords.replace(/ /, "");
			carr = coords.split(",");
			for (k = 0; k < carr.length; k += 2)
			{
				p.push({x: parseInt(carr[k]), y: parseInt(carr[k+1])})
			}
			allpolys.push(p);
		}
	}
	return allpolys;
}

function getRects()
{
	allrects = new Array();
	rects = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'rect') ? true : false; }, 'td', document);
	for (i = 0; i < rects.length; i++)
	{
		children = rects[i].childNodes;
		for (j = 0; j < children.length; j++)
		{
			p = new Array();
			coords = children[j].nodeValue;
			coords = coords.replace(/ /, "");
			carr = coords.split(",");
			allrects.push({x1: parseInt(carr[0]), y1: parseInt(carr[1]), x2: parseInt(carr[2]), y2: parseInt(carr[3])});
		}
	}
	return allrects;
}

function getCircles()
{
	allcircles = new Array();
	circles = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'circle') ? true : false; }, 'td', document);
	for (i = 0; i < circles.length; i++)
	{
		children = circles[i].childNodes;
		for (j = 0; j < children.length; j++)
		{
			p = new Array();
			coords = children[j].nodeValue;
			coords = coords.replace(/ /, "");
			carr = coords.split(",");
			allcircles.push({x: parseInt(carr[0]), y: parseInt(carr[1]), r: parseInt(carr[2])});
		}
	}
	return allcircles;
}

function mouseOverMap(e, obj)
{
	px = e.offsetX;
	py = e.offsetY;

	for (i = 0; i < a_polygons.length; i++)
	{
		if (isPointInPoly(a_polygons[i], { x: px, y: py }))
		{
			polygons = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'poly') ? true : false; }, 'td', document);
			for (j = 0; j < polygons.length; j++)
			{
				if (i == j)
				{
					polygons[j].parentNode.bgColor = '#fdfabb';
				}
				else
				{
					polygons[j].parentNode.bgColor = '';
				}
			}
		}
		else
		{
			polygons = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'poly') ? true : false; }, 'td', document);
			polygons[i].parentNode.bgColor = '';
		}
	}
	for (i = 0; i < a_rects.length; i++)
	{
		if (isPointInRect(a_rects[i], { x: px, y: py }))
		{
			circles = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'rect') ? true : false; }, 'td', document);
			for (j = 0; j < rects.length; j++)
			{
				if (i == j)
				{
					rects[j].parentNode.bgColor = '#fdfabb';
				}
				else
				{
					rects[j].parentNode.bgColor = '';
				}
			}
		}
		else
		{
			rects = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'rect') ? true : false; }, 'td', document);
			rects[i].parentNode.bgColor = '';
		}
	}
	for (i = 0; i < a_circles.length; i++)
	{
		if (isPointInCircle(a_circles[i], { x: px, y: py }))
		{
			circles = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'circle') ? true : false; }, 'td', document);
			for (j = 0; j < circles.length; j++)
			{
				if (i == j)
				{
					circles[j].parentNode.bgColor = '#fdfabb';
				}
				else
				{
					circles[j].parentNode.bgColor = '';
				}
			}
		}
		else
		{
			circles = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'circle') ? true : false; }, 'td', document);
			circles[i].parentNode.bgColor = '';
		}
	}	
}

function reindexRows(rootel)
{
	rows = YAHOO.util.Dom.getElementsBy(function (el) { return true; }, 'tr', rootel);
	for (i = 0; i < rows.length; i++)
	{
		// set row class
		YAHOO.util.Dom.removeClass(rows[i], "odd");
		YAHOO.util.Dom.removeClass(rows[i], "even");
		YAHOO.util.Dom.removeClass(rows[i], "first");
		YAHOO.util.Dom.removeClass(rows[i], "last");
		alter = (i % 2 == 0) ? "even" : "odd";
		YAHOO.util.Dom.addClass(rows[i], alter);
		add = (i == 0) ? "first" : ((i == rows.length-1) ? "last" : "");
		if (add.length > 0) YAHOO.util.Dom.addClass(rows[i], add);

		removebuttons = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'area_remove') ? true : false; }, 'input', rows[i]);
		for (j = 0; j < removebuttons.length; j++)
		{
			removebuttons[j].name = removebuttons[j].name.replace(/\[\\d+\]/, "[" + j + "]");
		}
	}
}

function removeRow(e, obj)
{
	row = this.parentNode.parentNode;
	tbody = row.parentNode;
	rows = YAHOO.util.Dom.getElementsBy(function (el) { return true; }, 'tr', tbody);
	tbody.removeChild(row);
	if (rows.length > 1)
	{
		reindexRows(tbody);
	}
}

function imagemapEvents(e)
{
	imagemaps = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'imagemap') ? true : false; }, 'img', document);
	for (i = 0; i < imagemaps.length; i++)
	{
		imagemap = imagemaps[i];
		YAHOO.util.Event.addListener(imagemap, 'mousemove', mouseOverMap);
	}

	removebuttons = YAHOO.util.Dom.getElementsByClassName('area_remove');
	for (i = 0; i < removebuttons.length; i++)
	{
		button = removebuttons[i];
		YAHOO.util.Event.addListener(button, 'click', removeRow);
	}
	
	a_polygons = getPolygons();
	a_rects = getRects();
	a_circles = getCircles();
}

function isPointInPoly(poly, pt){
	for(var c = false, i = -1, l = poly.length, j = l - 1; ++i < l; j = i)
		((poly[i].y <= pt.y && pt.y < poly[j].y) || (poly[j].y <= pt.y && pt.y < poly[i].y))
		&& (pt.x < (poly[j].x - poly[i].x) * (pt.y - poly[i].y) / (poly[j].y - poly[i].y) + poly[i].x)
		&& (c = !c);
	return c;
}

function isPointInCircle(circle, pt)
{
	square_dist = Math.pow((circle.x - pt.x),2) + Math.pow((circle.y - pt.y),2);
	return square_dist <= Math.pow(circle.r,2);
}

function isPointInRect(rect, pt)
{
	return pt.x >= rect.x1 && pt.x <= rect.x2 && pt.y >= rect.y1 && pt.y <= rect.y2; 
}

YAHOO.util.Event.onDOMReady(imagemapEvents);