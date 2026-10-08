/**
 * Keep React working on a page something else has rewritten.
 *
 * The browser's own page translation (Chrome's "Translate this page", Edge,
 * Safari) swaps every text node for a `<font>` of its own. React still holds
 * the original text nodes, so the next time it removes one, or inserts
 * something in front of one, the DOM refuses: "Failed to execute
 * 'removeChild' on 'Node': The node to be removed is not a child of this
 * node." -- and the whole page falls over, from as little as opening a
 * select. Some extensions rewrite pages the same way.
 *
 * Translation is something readers rely on, so instead of forbidding it the
 * two operations are made forgiving: a node that has already left its
 * parent counts as removed, and an insert whose landmark has gone lands at
 * the end instead. A translated page can then show a stale word until it
 * reloads, which is a far smaller harm than a page that stops working.
 *
 * @see https://github.com/facebook/react/issues/11538
 *
 * Call before createInertiaApp, so the first render is already covered.
 */
export function installForeignDomTolerance(): void {
    /** Read off the prototype, to be called with the node they belong to. */
    const removeChild = Reflect.get(
        Node.prototype,
        'removeChild',
    ) as Node['removeChild'];
    const insertBefore = Reflect.get(
        Node.prototype,
        'insertBefore',
    ) as Node['insertBefore'];

    Node.prototype.removeChild = function <T extends Node>(
        this: Node,
        child: T,
    ): T {
        if (child.parentNode !== this) {
            return child;
        }

        return removeChild.call(this, child) as T;
    };

    Node.prototype.insertBefore = function <T extends Node>(
        this: Node,
        node: T,
        child: Node | null,
    ): T {
        if (child !== null && child.parentNode !== this) {
            return insertBefore.call(this, node, null) as T;
        }

        return insertBefore.call(this, node, child) as T;
    };
}
