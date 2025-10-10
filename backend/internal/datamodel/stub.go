//go:build tools
// +build tools

package datamodel

import _ "github.com/shopmonkeyus/go-datamodel/v3"

// Package datamodel will encapsulate adapters between the Shopmonkey data model
// and our domain entities. The blank import preserves the dependency until we
// wire in the actual implementation.
