function cleanElements(rootel)
{
	imagepresentation = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'imagepresentation') ? true : false; }, 'div', rootel);
	for (i = 0; i < imagepresentation.length; i++) imagepresentation[i].parentNode.removeChild(imagepresentation[i]);
}

function reindexRows(rootel, postvar)
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

		// change id and name of file input fields
		fileinputs = YAHOO.util.Dom.getElementsBy(function (el) { return (el.type == 'file') ? true : false; }, 'input', rows[i]);
		for (j = 0; j < fileinputs.length; j++)
		{
			fileinputs[j].id = postvar + '[image][' + i + ']';
			fileinputs[j].name = postvar + '[image][' + i + ']';
		}

		// change name of hidden fields
		hidden = YAHOO.util.Dom.getElementsBy(function (el) { return (el.type == 'hidden') ? true : false; }, 'input', rows[i]);
		for (j = 0; j < hidden.length; j++)
		{
			if (hidden[j].name.indexOf('imagename') >= 0)
			{
				hidden[j].name = postvar + '[imagename][' + i + ']';
			}
			else if (hidden[j].name.indexOf('count') >= 0)
			{
				hidden[j].name = postvar + '[count][' + i + ']';
			}
		}

		// change id and name of file delete buttons
		buttons = YAHOO.util.Dom.getElementsBy(function (el) { return (el.type == 'submit') ? true : false; }, 'input', rows[i]);
		for (j = 0; j < buttons.length; j++)
		{
			if (buttons[j].name.indexOf('[upload') >= 0)
			{
				buttons[j].name = 'cmd[upload' + postvar + '][' + i + ']';
			} 
			else if (buttons[j].name.indexOf('[removeimage') >= 0)
			{
				buttons[j].name = 'cmd[removeimage' + postvar + '][' + i + ']';
			}
		}

		addbuttons = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'imagewizard_add') ? true : false; }, 'input', rows[i]);
		for (j = 0; j < addbuttons.length; j++)
		{
			addbuttons[j].id = 'add_' + postvar + '[' + i + ']';
			addbuttons[j].name = 'cmd[add' + postvar + '][' + i + ']';
			YAHOO.util.Event.addListener(addbuttons[j], 'click', addRow);
		}
		removebuttons = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'imagewizard_remove') ? true : false; }, 'input', rows[i]);
		for (j = 0; j < removebuttons.length; j++)
		{
			removebuttons[j].id = 'remove_' + postvar + '[' + i + ']';
			removebuttons[j].name = 'cmd[remove' + postvar + '][' + i + ']';
			YAHOO.util.Event.addListener(removebuttons[j], 'click', removeRow);
		}
		upbuttons = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'imagewizard_up') ? true : false; }, 'input', rows[i]);
		if (upbuttons.length > 0)
		{
			for (j = 0; j < upbuttons.length; j++)
			{
				upbuttons[j].id = 'up_' + postvar + '[' + i + ']';
				upbuttons[j].name = 'cmd[up' + postvar + '][' + i + ']';
				YAHOO.util.Event.addListener(upbuttons[j], 'click', moveRowUp);
			}
		}
		downbuttons = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'imagewizard_down') ? true : false; }, 'input', rows[i]);
		if (downbuttons.length > 0)
		{
			for (j = 0; j < downbuttons.length; j++)
			{
				downbuttons[j].id = 'down_' + postvar + '[' + i + ']';
				downbuttons[j].name = 'cmd[down' + postvar + '][' + i + ']';
				YAHOO.util.Event.addListener(downbuttons[j], 'click', moveRowDown);
			}
		}
	}
}

function removeListeners(rootel)
{
	var addbuttons = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'imagewizard_add') ? true : false; }, 'input', rootel);
	for (i = 0; i < addbuttons.length; i++)
	{
		YAHOO.util.Event.purgeElement(addbuttons[i]);
	}
	var removebuttons = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'imagewizard_remove') ? true : false; }, 'input', rootel);
	for (i = 0; i < removebuttons.length; i++)
	{
		YAHOO.util.Event.purgeElement(removebuttons[i]);
	}
	var upbuttons = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'imagewizard_up') ? true : false; }, 'input', rootel);
	for (i = 0; i < upbuttons.length; i++)
	{
		YAHOO.util.Event.purgeElement(upbuttons[i]);
	}
	var downbuttons = YAHOO.util.Dom.getElementsBy(function (el) { return (el.className == 'imagewizard_down') ? true : false; }, 'input', rootel);
	for (i = 0; i < downbuttons.length; i++)
	{
		YAHOO.util.Event.purgeElement(downbuttons[i]);
	}
}

function addRow(e, obj)
{
	var row = this.parentNode.parentNode;
	var tbody = row.parentNode;
	
	removeListeners(tbody);
	rowclone = row.cloneNode(true);
	cleanElements(rowclone);
	
	trs = YAHOO.util.Dom.getElementsBy(function (el) { return true; }, 'tr', tbody);
	parentindex = 0;
	for (i = 0; i < trs.length; i++)
	{
		if (trs[i] == row) parentindex = i+1;
	}
	if (parentindex == trs.length)
	{
		tbody.appendChild(rowclone);
	}
	else
	{
		tbody.insertBefore(rowclone, trs[parentindex]);
	}
	reindexRows(tbody, tbody.parentNode.parentNode.id);
	textinputs = YAHOO.util.Dom.getElementsBy(function (el) { return (el.type == 'text') ? true : false; }, 'input', rowclone);
	textinputs[0].focus();
	return false;
}

function removeRow(e, obj)
{
	var row = this.parentNode.parentNode;
	var tbody = row.parentNode;
	trs = YAHOO.util.Dom.getElementsBy(function (el) { return true; }, 'tr', tbody);
	if (trs.length == 1)
	{
		cleanElements(trs[0]);
	}
	else
	{
		tbody.removeChild(row);
		removeListeners(tbody);
		reindexRows(tbody, tbody.parentNode.parentNode.id);
	}
}

function moveRowUp(e, obj)
{
	var row = this.parentNode.parentNode;
	var tbody = row.parentNode;
	rows = YAHOO.util.Dom.getElementsBy(function (el) { return true; }, 'tr', tbody);
	foundindex = 0;
	for (i = 0; i < rows.length; i++)
	{
		if (rows[i] == row) foundindex = i;
	}

	if (foundindex > 0)
	{
		removeListeners(tbody);
		temp = rows[foundindex-1];
		rows[foundindex-1] = row;
		rows[foundindex] = temp;
		children = tbody.childNodes;
		for (j = 0; j < children.length; j++)
		{
			tbody.removeChild(children[j]);
		}
		for (j = 0; j < rows.length; j++)
		{
			tbody.appendChild(rows[j]);
		}
		reindexRows(tbody, tbody.parentNode.parentNode.id);
	}
}

function moveRowDown(e, obj)
{
	var row = this.parentNode.parentNode;
	var tbody = row.parentNode;
	rows = YAHOO.util.Dom.getElementsBy(function (el) { return true; }, 'tr', tbody);
	foundindex = 0;
	for (i = 0; i < rows.length; i++)
	{
		if (rows[i] == row) foundindex = i;
	}

	if (foundindex < rows.length-1)
	{
		removeListeners(tbody);
		temp = rows[foundindex+1];
		rows[foundindex+1] = row;
		rows[foundindex] = temp;
		children = tbody.childNodes;
		for (j = 0; j < children.length; j++)
		{
			tbody.removeChild(children[j]);
		}
		for (j = 0; j < rows.length; j++)
		{
			tbody.appendChild(rows[j]);
		}
		reindexRows(tbody, tbody.parentNode.parentNode.id);
	}
}

function imageWizardEvents(e)
{
	addbuttons = YAHOO.util.Dom.getElementsByClassName('imagewizard_add');
	for (i = 0; i < addbuttons.length; i++)
	{
		button = addbuttons[i];
		YAHOO.util.Event.addListener(button, 'click', addRow);
	}
	removebuttons = YAHOO.util.Dom.getElementsByClassName('imagewizard_remove');
	for (i = 0; i < removebuttons.length; i++)
	{
		button = removebuttons[i];
		YAHOO.util.Event.addListener(button, 'click', removeRow);
	}
	upbuttons = YAHOO.util.Dom.getElementsByClassName('imagewizard_up');
	for (i = 0; i < upbuttons.length; i++)
	{
		button = upbuttons[i];
		YAHOO.util.Event.addListener(button, 'click', moveRowUp);
	}
	downbuttons = YAHOO.util.Dom.getElementsByClassName('imagewizard_down');
	for (i = 0; i < downbuttons.length; i++)
	{
		button = downbuttons[i];
		YAHOO.util.Event.addListener(button, 'click', moveRowDown);
	}
}

YAHOO.util.Event.onDOMReady(imageWizardEvents);