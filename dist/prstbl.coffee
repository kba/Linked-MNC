#!/usr/bin/env coffee

fs = require 'fs'
xml2js = require 'xml2js'

fname = process.argv[2]

ret = {}

fs.readFile fname, (err, buf) ->
	throw err if err
	xml2js.parseString buf.toString(), (err, obj) ->
		for rowId, row of obj.table.tr

			continue if row.td[0].strong
			id = row.td[0].replace('.', '')
			caption = row.td[1]
			if id.indexOf('-') < 0
				ret[id] = caption
			# else
				# bounds = id.split(/\s*-\s*/)
				# left = parseInt bounds[0]
				# right = parseInt bounds[1]
				# # if left > right or right - left > 100
					# # ret[right] = caption
				# # else
					# for subid in [left .. right]
						# ret[subid] = caption
	out = {}
	for k, v of ret
		out_k = k
		if k.length > 3
			out_k = k.substr(0,3) + '.' + k.substr(3)
		out[out_k] = v

	console.log out
